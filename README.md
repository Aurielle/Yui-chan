# Yui-chan
I'm Yui! Glad to be working with you all! I love GDM concerts, and if you will be nice to me, I'll help you download tons of images from your favorite imageboard! Okay? ^_^

![Yui-chan](http://yui.aurielle.cz/images/yui.png)

## Setup
- Get and install PHP 5.3.2 or greater and perform necessary setup for command-line usage.
- Get and install [Composer](http://getcomposer.org/doc/01-basic-usage.md#installation).
- Download Yui-chan!
- Run `composer install` in the folder with Yui. This will download dependencies.
- Yui-chan is ready to work!

## Usage
Yui is usable only through command line (CLI). To see all available options of Yui, run `php yui.php`. To see available options of certain command, run `php yui.php help <command>`.

### Downloading from your favorite imageboard
Run `php yui.php <imageboard>`. Yui will ask you for the tag or tags you want to download, and pages you want to download. Pictures will then be downloaded to directory download/<tag> (relative to Yui directory). Yui-chan supports these imageboards:
- Konachan.com (command is `konachan`)
- Yande.re (command is `yandere`)
- Danbooru (command is `danbooru`)
- more are coming!

You can also specify options:
`php yui.php <imageboard> [-p|--page x-y] [-a|--all] [-d|--dir <local dir>] [--timeout X] tag1 [tag2] ... [tagN]`

Both options and user input can be combined.

#### Arguments
Separate tags with spaces, as in Konachan's or any other imageboard's search. If your tags contain special control characters (such as < or >), enclose all tags in quotes.
Examples:
- `php yui.php konachan sakura_kyouko`
- `php yui.php konachan yui_(angel_beats!)`
- `php yui.php konachan "sakura_kyouko width:>=1920"`


#### Options
- `--page` or `-p` - specifies page or pages to download. Use single number (3) or range with dashes (5-12).
- `--all` or `-a` - option to download all pages. If both --all and --pages are specified, you will be asked to choose between these two.
- `--dir` or `-d` - specifies the directory in local filesystem, in which will Yui download the pictures. If not present, defaults to <directory with Yui>/download/<tag>. PHP (-> user, under which you run Yui) must have read+write permissions to this directory.
- `--timeout` - specifies time to wait (in seconds) when downloading pictures. Defaults to 30 seconds, after this time will the request time out and the picture won't be downloaded.

### Full usage example
Downloads pictures with tag `sakura_kyouko` from Danbooru, pages 35 to 93 to directory `/home/aurielle/danbooru`. Timeout option is not present and defaults to 30 seconds.
```
php yui.php danbooru --dir "/home/aurielle/danbooru" -p 35-93 sakura_kyouko
```

Downloads all pictures matching search of tags `sakura_kyouko width:>=1920 rating:all` from Konachan, into current Yui directory (-> `<yui dir>/download/sakura_kyouko`). Timeout for requests is 60 seconds.
```
php yui.php konachan -a --timeout 60 "sakura_kyouko width:>=1920 rating:all"
```

Interactive download (from Konachan, into current Yui directory) + output:

```
$ php yui.php konachan
Enter tag(s), which you want to download:
kousaka_kirino gokou_ruri rating:questionable
Do you want to download all pictures?
y
Okay! Yui-chan will now download your pictures!
Selected tags: kousaka_kirino gokou_ruri rating:questionable, pages: all
Fetching tag information...
----- Found ~79 pictures on 1 pages. -----

--- Downloading images from page 1 ---
Downloading image #1 from page 1.
Downloading image #2 from page 1.
Downloading image #3 from page 1.
Downloading image #4 from page 1.
Downloading image #5 from page 1.
Downloading image #6 from page 1.
Downloading image #7 from page 1.
Downloading image #8 from page 1.
Downloading image #9 from page 1.
Downloading image #10 from page 1.
Downloading image #11 from page 1.
Downloading image #12 from page 1.
Downloading image #13 from page 1.
Downloading image #14 from page 1.
Downloading image #15 from page 1.
Downloading image #16 from page 1.
Downloading image #17 from page 1.

Yui has finished! ^_^
```