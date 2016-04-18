# SBTS Cloudfiles Uploader
Add wordpress uploads to cloud files, manage links to serve only cdn links to cloud files, and add custom upload manager.

## Installation
In your plugins directory (/wordpress/wp-content/plugins/):  
    $ git clone git@github.com:sbtsdev/sbts-cf-uploader.git  
    $ cd sbts-cf-uploader  
    $ composer install  

## Configuration
1. Log into Wordpress
2. Go to Plugins
3. Enable Cloud Files Uploader
4. Go to Settings > Media
5. Under Cloud Files Settings add your Cloud Files username and API key  
    A. Add your Cloud Files username  
    B. Add your Cloud Files API key  
    C. Save the Media settings  
    D. Add the Cloud Files container that Wordpress will use to upload files to  

## Usage
Wordpress media uploads can be used normally. Uploaded media will be put on Cloud Files and references to Cloud files will
be saved with the media.

Under Media there is also a Cloud Files Uploader. This gives the user the ability to manually upload files as well as view uploaded contents for each container in the Cloud Files account.

## Access
Only users who can upload files are able to use the uploader. This is a default wordpress permission.
