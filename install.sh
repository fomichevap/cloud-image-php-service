#!/bin/bash

# ====================================================
# Установочный скрипт для развертывания микросервиса.
#
# Действия:
# 1. Проверяет наличие sqlite3 и устанавливает его при необходимости.
# 2. Проверяет, что установлен расширение PDO SQLite для PHP.
# 3. Создаёт директорию data/ (если отсутствует).
# 4. Создаёт базы данных dev.db и prod.db в data/:
#    - dev.db создаётся, если отсутствует.
#    - Если prod.db уже существует, он не перезаписывается и выводится предупреждение.
# 5. Выполняет SQL‑скрипт install/db.sql для каждой созданной базы.
# ====================================================

set -x

# Функция для установки sqlite3 через менеджер пакетов
install_sqlite() {
    if command -v apt-get >/dev/null 2>&1; then
        echo "Устанавливаем sqlite3 через apt-get..."
        sudo apt-get update && sudo apt-get install -y sqlite3
    elif command -v yum >/dev/null 2>&1; then
        echo "Устанавливаем sqlite3 через yum..."
        sudo yum install -y sqlite
    else
        echo "Не удалось обнаружить apt-get или yum. Пожалуйста, установите sqlite3 вручную."
        exit 1
    fi
}

# Проверка наличия sqlite3
if ! command -v sqlite3 >/dev/null 2>&1; then
    echo "sqlite3 не найден на системе."
    install_sqlite
else
    echo "sqlite3 найден: $(sqlite3 --version)"
fi

# Проверка наличия расширения pdo_sqlite для PHP
if ! php -m | grep -qi pdo_sqlite; then
    echo "Расширение PDO SQLite не найдено. Пытаемся установить..."
    if command -v apt-get >/dev/null 2>&1; then
        sudo apt-get update && sudo apt-get install -y php-sqlite3
    elif command -v yum >/dev/null 2>&1; then
        sudo yum install -y php-sqlite3
    else
        echo "Невозможно обнаружить пакетный менеджер, установите расширение PDO SQLite вручную."
        exit 1
    fi
else
    echo "Расширение PDO SQLite установлено."
fi

# Создание директории data/, если не существует
if [ ! -d "data" ]; then
    echo "Создаём директорию data/..."
    mkdir data
fi

# Проверка наличия файла install/db.sql
if [ ! -f "install/db.sql" ]; then
    echo "Файл install/db.sql не найден. Проверьте структуру проекта."
    exit 1
fi

# Функция для создания базы данных и выполнения SQL-скрипта установки.
# Если база уже существует и это prod.db, выводит предупреждение и пропускает создание.
create_database() {
    local db_file=$1
    if [ -f "$db_file" ]; then
        if [[ "$db_file" == *"prod.db" ]]; then
            echo "Предупреждение: База данных $db_file уже существует. Перезапись не требуется."
            return
        else
            echo "База данных $db_file уже существует."
            return
        fi
    fi
    echo "Создаём базу данных $db_file..."
    sqlite3 "$db_file" < install/db.sql

    FIRST_APP_TOKEN=$(openssl rand -hex 16)
    echo "First app token (root on $db_file): $FIRST_APP_TOKEN"

    # Write SQL commands to a temporary file
    SQL_FILE=$(mktemp /tmp/init_app.XXXXXX.sql)
    cat > "$SQL_FILE" <<SQL
        INSERT INTO app (title, token, created)
        VALUES ('self', '$FIRST_APP_TOKEN', strftime('%s','now'));
    SQL

    # Apply the SQL file to the database
    sqlite3 $db_file < "$SQL_FILE"

    # Remove the temporary SQL file
    rm -f "$SQL_FILE"

    echo "Please save this root token for API access on $db_file: $FIRST_APP_TOKEN"

    if [ $? -eq 0 ]; then
        echo "База данных $db_file успешно создана."
    else
        echo "Ошибка при создании базы $db_file."
        exit 1
    fi
}

# Создаём базы данных dev.db и prod.db
create_database "data/dev.db"
create_database "data/prod.db"



mv config_default.php config.php

echo "Установка завершена, убедитесь, что config.php настроен корректно."
