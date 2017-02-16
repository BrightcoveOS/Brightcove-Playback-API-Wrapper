# Brightcove Playback API Wrapper

This is a PHP wrapper for the [Brightcove Playback API](https://docs.brightcove.com/en/video-cloud/playback-api/)

## Getting Started

Add files to project folder. 

In get-brighcove-media.php replace the policy token and account number when the class is instantiated

To obtain policy key see this doc: http://docs.brightcove.com/en/video-cloud/brightcove-player/guides/policy-key.html
or use the web app to get one directly from the Policy API: http://docs.brightcove.com/en/video-cloud/policy-api/getting-started/quick-start.html#Set_policy_app

File caching layer is activated by adding require_once('bc-papi-cache.php') to get-brightcove-media.php

Access api as follows (e.g. files placed in folder named brightcove-playback-api):

```
require_once($_SERVER['DOCUMENT_ROOT'] . '/brightcove-playback-api/get-brightcove-media.php');
//find video by video id
$videoid = 12345;
$video = $bc->find('find_video_by_id', 'videos', $videoid);
// e.g. get video poster and display
echo '<img src="' . $video->poster . '" />';

//find video by reference id
$refid = ref123;
$video = $bc->find('find_video_by_reference_id', 'videos', $ref123);

//find playlist by video id
$videoid = 54321;
$playlist = $bc->find('find_playlist_by_id', 'playlists', $54321);
echo '<img src="' . $playlist->videos[0]->poster . '" />';

//find playlist by reference id
$refid = ref321;
$playlist = $bc->find('find_playlist_by_reference_id', 'playlists', $ref321);

```


## Authors

* **Theresa Newman** - *Initial work* - [Brightcove-Playback-API-Wrapper](https://github.com/theresaweb/Brightcove-Playback-API-Wrapper)

## Acknowledgments

* Based on the PHP Wrapper for the Media API which is being deprecated (https://github.com/BrightcoveOS/PHP-MAPI-Wrapper)
