Yui-chan
========

I'm Yui! Glad to be working with you all! I love GDM concerts, and if you will be nice to me, I'll help you download tons of images from your favorite imageboard! Okay? ^_^

![Yui-chan](http://yui.aurielle.cz/images/yui.png)

Setup
-----
- Get and install PHP 5.3.2 or greater and perform necessary setup for command-line usage.
- Get and install [Composer](http://getcomposer.org/doc/01-basic-usage.md#installation).
- Download Yui-chan!
- Run `composer install` in the folder with Yui. This will download dependencies.
- Yui-chan is ready to work!

Usage
-----
Yui is usable only through command line (CLI). To see all available options of Yui, run `php yui.php`. To see available options of certain command, run `php yui.php help <command>`.

### Downloading from Konachan.com
Run `php yui.php konachan`. Yui will ask you for the tag or tags you want to download, and pages you want to download. Pictures will then be downloaded to directory download/<tag> (relative to Yui directory).

Or you can specify options:
`php yui.php [-p|--page x-y] [-a|--all] [-d|--dir <local dir>] [--timeout X] tag1 [tag2] ... [tagN]`

Both options and user input can be combined.

#### Arguments
Separate tags with spaces, as in Konachan's search. If your tags contain special control characters (such as < or >), enclose all tags in quotes.
Examples:
- `php yui.php konachan sakura_kyouko`
- `php yui.php konachan yui_(angel_beats!)`
- `php yui.php konachan "sakura_kyouko width:>=1920"`


#### Options
- `--page` or `-p` - specifies page or pages to download. Use single number (3) or range with dashes (5-12).
- `--all` or `-a` - option to download all pages. If both --all and --pages are specified, you will be asked to choose between these two.
- `--dir` or `-d` - specifies the directory in local filesystem, in which will Yui download the pictures. If not present, defaults to <directory with Yui>/download/<tag>.
- `--timeout` - specifies time to wait (in seconds) when downloading pictures. Defaults to 30 seconds, after this time will the request time out and the picture won't be downloaded.