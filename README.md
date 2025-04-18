# CESAM'S
#### current version 0.2
# *CLOUD STORAGE API IMAGE SERVICE*
microservice to storage images with tags,resizing and caching + widget for portal to manage images

using sqlite3 (be sure that PDO driver is installed for your PHP version, **usually** this is not installed by default)

## Requirements
>PHP 7.2 - 8.2
>Sqlite3
>Imagick PHP Module
## install
``` git clone https://storage-service.sfck.ru/microservice/image-cloud.git 
git pull origin master
cd image-cloud
chmod +x install.sh
./install.sh
nano config.php
```
Next change config.php - you need to define ```BASE_URL``` to correct work
>**notes**: 
be sure that ./image-cloud/ is visible from web
This is 2 databases - dev.db, prod.db
default use is dev.db
when you install, it makes one app token for each env (prod and dev), please write it
after install you need to get access_token if you want to use api


## API

### use
all of methods (exact api/GetAccessToken) need to provide access_token in POST
Only root app (first) can create other apps
if you want to provide role to create apps, you need manually set root_flag to app in db (app table, cell "root_flag" 0 => 1)
Each of request make expired longer for your access token

### create app

**/api/CreateApp**
title - title to app, returns token

### get access token

**/api/GetAccessToken**
provide token in payload to get access token
example: ```{"token":"THIS_IS_YOUR_TOKEN"}```
output ```{"access_token":"YOUR_ACCESS_TOKEN","expired":123456789}```

### Upload images
**/api/Receive**

**POST** method

```
file: file (jpeg,jpg,png)
payload: json {"tags":["tag1","tag2",...]}
```
### Modify image

**/api/AddTagToImage**

**POST** method

```
payload: json {"image_id":int,"tags":["tag1","tag2",...]}
```
**/api/DeleteImage**

**POST** method
Marked image as deleted (no physical delete)

```
payload: json {"image_id":int}
```
**/api/RotateImage**
rotating image (90 degrees, Left or Right direction)

**POST** method

```
payload: json {"image_id":int,"direction":"R|L"}
```

### Get exists tags
**/api/GetTagList**

output: json

## GETTING IMAGES

example.com/image/WIDTH(_xHEIGHT_)/_tag1_/_tag2_/(**_index_**)

you need to describe width or width and height separated by "x",
then you can provide tags you wish, last is image index (1,... n)

If there no image with this index, it will count image cycled and give you some (always same image via one index)

Also you can use "original" instead WIDTH(_xHEIGHT_), example: example.com/image/**_original_**/_tag1_/_tag2_/(**_index_**)

Also you can use random_SALT instead index, where SALT is some random string. This way service will provide some random photo for this salt (same image for one user in one "session"). You can set "session" time in config.php
> session  is not a usual session, this is set-up user-agent, ip and route for image, it stores in DB before expiration time


If there no image with such tags combination, you will receive `/storage/noimage.jpg`

You can change /storage/noimage.jpg by you wish

## Management Widget

<img src='https://cloud.selfclick.ru/image/original/screenshot/1'></img>

### Installation && use
> Widget receive apptoken from backend for work. Be sure that your widget and widget url is closed from outside

>```<script src="https://example.com/widget/MD5_OF_TOKEN>/cesams.js">```
```<div id="cesams"></div>```
```<script>cesams.init('cesams')</script>```