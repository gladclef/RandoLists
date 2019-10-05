<?php

require_once(dirname(__FILE__) . "/load_session.php");

$api_key = parse_ini_file("secret.ini");
$s_api_id = $api_key['client_id'];
$s_api_secret = $api_key['client_secret'];
$as_scopes = ['user-library-read', 'playlist-read-collaborative', 'playlist-read-private', 'playlist-modify-private', 'streaming', 'app-remote-control', 'user-read-currently-playing'];

$b_loggedin = FALSE;
$s_loginerr = "";
if (isset($_GET['logout']))
{
	unset($_SESSION['access_token']);
	unset($_SESSION['authcode']);
	unset($_SESSION['state']);
	unset($_SESSION['expires_in']);
	unset($_SESSION['access_acquire_time']);
	unset($_SESSION['refresh_token']);
	$_SESSION['show_dialog'] = TRUE; // forces the login prompts to be displayed next time the user tries to log in
}
if (!isset($_SESSION['state']))
{
	$_SESSION['state'] = rand();
}
if (!isset($_SESSION['access_token']))
{
	$accessToken = "";
	if (isset($_GET['error']))
	{
		$s_loginerr = "<br /><div style='color:red;'>Error logging in: {$_GET['error']}</div>";
	}
	else if (isset($_GET['code']))
	{
		$_SESSION['authcode'] = $_GET['code'];

		$options = array(
		    'http' => array( // use key 'http' even if you send the request to https://...
		        'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
		        'method'  => 'POST',
		        'content' => http_build_query(array(
					'grant_type' => 'authorization_code',
					'code' => $_SESSION['authcode'],
					'redirect_uri' => 'https://bbean.us/randolists/index.php',
					'client_id' => $s_api_id,
					'client_secret' => $s_api_secret
				)),
		    )
		);
		$context  = stream_context_create($options);
		$sb_result = file_get_contents('https://accounts.spotify.com/api/token', false, $context);

		$a_token = array();
		if ($sb_result === FALSE)
		{
			$s_loginerr = "<br /><div style=color:red;'>Error getting access token</div>";
		}
		else
		{
			$a_token = json_decode($sb_result, TRUE);
			if (isset($a_token['access_token']))
			{
				$_SESSION['access_token']        = $a_token['access_token'];
				$_SESSION['expires_in']          = $a_token['expires_in'];
				$_SESSION['access_acquire_time'] = time();
				$_SESSION['refresh_token']       = $a_token['refresh_token'];
				$accessToken                     = $_SESSION['access_token'];
				$b_loggedin                      = TRUE;
			}
			else
			{
				$s_loginerr = "<br /><div style='color:red;'>Error logging in: access_token not returned from spotify</div>";
			}
		}
	}
}
else
{
	$b_loggedin = TRUE;
	$accessToken = $_SESSION['access_token'];
}

$i_expires_in = 0;
if ($b_loggedin)
{
	$i_current = time();
	$i_expires_in = $_SESSION['expires_in'] - ($i_current - $_SESSION['access_acquire_time']);
}

?>

<html>
	<head>
		<script src="/jquery/js/jquery-3.4.1.js"></script>
		<script src="/jquery-ui/jquery-ui.min.js"></script>
		<style type="text/css">
			#login {
				display: <?php echo (($b_loggedin) ? "none" : "block"); ?>;
			}

			#loggedin {
				display: <?php echo (($b_loggedin) ? "block" : "none"); ?>;
			}
		</style>
	</head>
	<body style="background-image: linear-gradient(to bottom, #77f, #f97); background-repeat: no-repeat; background-color: #f97">
		<script>
			window.accessToken = "<?php echo $accessToken; ?>";
			window.refreshToken = "<?php echo $_SESSION['refresh_token']; ?>";
			window.expires_in = <?php echo $i_expires_in ?>;

			window.refreshAuthorization = function() {
				var failedToRefresh = function() {
					clearTimeout(checkRefreshInterval);
				};
				$.ajax({
					url: "https://bbean.us/randolists/refreshAuthToken.php",
					async: true,
					cache: false,
					type: "POST",
					data: {
						'do_refresh_authorization': true
					},
					timeout: 10000,
					success: function(response) {
						if (response == "") {
							console.log("no response to request to refreshAuthToken");
							return;
						}
						console.log(response);
						if (response.startsWith("access_token=")) {
							window.accessToken = response.substring("access_token=".length);
							expires_in = 3500;
						} else {
							failedToRefresh();
						}
					},
					error: function(a,b,c) {
						console.log(a);
						console.log(b);
						console.log(c);
						failedToRefresh();
					}
				});
			};
			window.checkRefreshAuthorization = function() {
				expires_in -= Math.min(20, expires_in);
				if (expires_in > 40) {
					return;
				} else {
					refreshAuthorization();
				}
			};

			window.librarySize = 0;
			window.userID = "[uid]";
			$(document).ready(function() {
				
				// set the height of the body to the height of the document or window
				var winheight = Math.max($(document).height(), $(window).height());
				if ($("body").height() < winheight)
				{
					$("body").css('min-height', winheight - 50);
				}
				$("#switchScreen").css({
					'width': $("body").width() + "px",
					'top': ($("body").height() / 2 - 50) + "px"
				});

				// make the logout box visible
				$(".uimage").each(function(k, imgtag) {
					var jimage = $(imgtag);
					var jdiv = jimage.parent();
					var jlogout = $(jdiv.siblings()[0]);
					jdiv.mouseover(function() {
						jlogout.show();
					});
					jlogout.mouseleave(function() {
						jlogout.hide();
					});
				});

				// get the user info
				<?php echo (($b_loggedin) ? "getUserInfo();" : ""); ?>

				// register the refreshAuthorization callback
				if (expires_in > 20) {
					window.checkRefreshInterval = setInterval(checkRefreshAuthorization, 20 * 1000);
				}
			});

			window.ajaxErr = function(xhr, ajaxOptions, thrownError, retryFunc, failureCallback) {
				if (xhr.status == 429 && retryFunc !== undefined && retryFunc !== null) {
					var i_retryAfter = xhr.getResponseHeader('Retry-After');
					i_retryAfter = (i_retryAfter !== null) ? parseInt(i_retryAfter) : 1;
					setTimeout(retryFunc, i_retryAfter * 1000);
				}
				else
				{
					if (failureCallback !== undefined && failureCallback !== null)
					{
						return failureCallback(xhr, ajaxOptions, thrownError);
					}
					console.log(xhr);
					console.log(ajaxOptions);
					console.log(thrownError);
					if (parseInt(xhr.status) == 0 && thrownError && (thrownError+"").indexOf("NETWORK_ERR") > -1) {
						alert("network error encountered");
						return;
					}
					alert("Error sending request: ("+xhr.status+") "+thrownError + ". Try logging out and back in.");
				}
			};

			window.getUserInfo = function()
			{
				$.ajax({
					url: "https://api.spotify.com/v1/me",
					async: true,
					cache: false,
					headers: {
						'Authorization': 'Bearer ' + accessToken
					},
					data: {
						"grant_type": "authorization_code",
						"code": "{$_SESSION['authcode']}",
						"redirect_uri": "https://bbean.us/randolists/index.php"
					},
					type: "GET",
					timeout: 10000,
					error: function(a,b,c) { ajaxErr(a,b,c,getUserInfo); },
					success: function(response) {
						userID = response.id;
						$(".uname").html(response.display_name);
						if (response.images && response.images.length > 0)
						{
							$(".uimage").each(function(k, imgtag) {
								var jimage = $(imgtag);
								var jdiv = jimage.parent();
								jimage.on("load", function() {
									var mindim = Math.min(imgtag.height, imgtag.width);
									if (mindim < jdiv.width())
									{
										jdiv.css('background-size', (jdiv.width() / mindim * 100) + "%");
									}
									jdiv.css('background-image', 'url("' + response.images[0].url + '")');
								});
								var jlogout = $(jdiv.siblings()[0]);
								jdiv.mouseover(function() {
									jlogout.show();
								});
								jlogout.mouseleave(function() {
									jlogout.hide();
								});
							});
							$(".uimage").attr('src', response.images[0].url);
						}
					}
				});
			};

			window.logout = function()
			{
				window.location.replace("https://bbean.us/randolists/index.php?logout");
			};

			window.getTrack = function(i_idx, successCallback, failureCallback)
			{
				var retryFunc = function() {
					return getTrack(i_idx, successCallback, failureCallback);
				};
				$.ajax({
					url: "https://api.spotify.com/v1/me/tracks",
					async: true,
					cache: false,
					headers: {
						'Authorization': 'Bearer ' + accessToken
					},
					data: {
						"limit": 1,
						"offset": i_idx
					},
					type: "GET",
					timeout: 10000,
					error: function(a,b,c) { ajaxErr(a,b,c,retryFunc,failureCallback); },
					success: function(response) {
						librarySize = response.total;
						successCallback(response.items[0].track);
					}
				});
			};

			window.getCurrentPlaying = function(successCallback, failureCallback)
			{
				var retryFunc = function() {
					return getTrack(i_idx, successCallback, failureCallback);
				};
				$.ajax({
					url: "https://api.spotify.com/v1/me/player/currently-playing",
					async: true,
					cache: false,
					headers: {
						'Authorization': 'Bearer ' + accessToken
					},
					type: "GET",
					timeout: 10000,
					error: function(a,b,c) { ajaxErr(a,b,c,retryFunc,failureCallback); },
					success: function(response) {
						if (response !== undefined && response !== null) {
							successCallback(response);
						}
					}
				});
			};

			window.drawCurrentlyPlaying = function()
			{
				getCurrentPlaying(function(track) {
					var album = track.item.album;
					var images = album.images;
					if (images.length == 0)
						return;
					var largestImage = "";
					var largestWidth = 0;
					for (var i = 0; i < images.length; i++)
					{
						if (images[i].width > largestWidth)
						{
							largestWidth = images[i].width;
							largestImage = images[i].url;
						}
					}
					var jwindow = $(window);
					var jplayDiv = $("#playerDiv");
					var jimage = jplayDiv.children("img");
					jimage.attr("src", largestImage);
					var widthHeight = Math.min(jwindow.width(), jwindow.height()) - 20;
					jimage.css({
						'width': widthHeight + "px",
						'height': widthHeight + "px"
					});
				}, function(a,b,c) {
					clearTimeout(playerInterval);
					ajaxErr(a,b,c,null,null);
				});
			};

			window.switchScreens = function()
			{
				var jrandoDiv = $("#randoListsDiv");
				var jplayDiv = $("#playerDiv");
				if (jrandoDiv.css('display') == 'none')
				{
					jrandoDiv.show();
					jplayDiv.hide();
				}
				else
				{
					jrandoDiv.hide();
					jplayDiv.show();
				}
			};

			window.setProgress = function(text, value, max)
			{
				$("#progressStatus").html(text);
				$("#progressValue").attr("value", value);
				$("#progressValue").attr("max", max);
			};

			window.getLibrarySize = function(successCallback, failureCallback)
			{
				setProgress("determining library size", 0, 30);
				var findSize = null;
				findSize = function() {
					getTrack(0,
						function(o_track) {
							setProgress("determining library size (" + librarySize + ")", 30, 30);
							successCallback(librarySize);
						},
						function(xhr, ajaxOptions, thrownError) {
							if (xhr.status == 502) {
								librarySize = 0;
								setProgress("determining library size (" + librarySize + ")", 30, 30);
								successCallback(librarySize);
							} else {
								ajaxErr(xhr, ajaxOptions, thrownError, findSize, failureCallback);
							}
						}
					);
				};
				findSize();
				setProgress("determining library size", 15, 30);
			};

			window.getPlaylist = function(s_name, successCallback, failureCallback)
			{
				setProgress("finding existing playlist", 0, 30);

				var idx = 0;
				var getPlaylists = null;
				getPlaylists = function() {
					$.ajax({
						url: "https://api.spotify.com/v1/me/playlists",
						async: true,
						cache: false,
						headers: {
							'Authorization': 'Bearer ' + accessToken
						},
						data: {
							"limit": 50,
							"offset": idx
						},
						type: "GET",
						timeout: 10000,
						error: function(a,b,c) { ajaxErr(a,b,c,getPlaylists,failureCallback); },
						success: function(response) {
							var b_found = false;
							var s_id = "";
							$.each(response.items, function(k, playlist) {
								if (playlist.name == s_name) {
									s_id = playlist.id;
									b_found = true;
								}
							});
							if (b_found) return successCallback(true, s_id);
							if (idx + 50 > response.total) {
								return successCallback(false, null);
							};
							idx += 50;
							getPlaylists();
							setProgress("finding existing playlist", idx, response.total);
						}
					});
				};
				getPlaylists();
			};

			window.clearPlaylist = function(s_name, s_id, successCallback, failureCallback)
			{
				var s_progtext = "clearing playlist \"" + s_name + "\"";
				setProgress(s_progtext, 0, 30);
				var numRemoved = 0;
				var numTracks = 0;
				var getTracks = null;
				var removeTracks = null;
				removeTracks = function(tracks) {
					var uris = [];
					$.each(tracks.items, function(k, trackval) {
						uris[uris.length] = { 'uri': trackval.track.uri };
					});
					$.ajax({
						url: "https://api.spotify.com/v1/playlists/" + s_id + "/tracks",
						async: true,
						cache: false,
						headers: {
							'Authorization': 'Bearer ' + accessToken,
							'Content-Type': 'application/json'
						},
						data: JSON.stringify({
							"tracks": uris
						}),
						type: "DELETE",
						timeout: 10000,
						error: function(a,b,c) { ajaxErr(a,b,c,removeTracks,failureCallback); },
						success: function(response) {
							numRemoved += Math.min(tracks.total, 100);
							setProgress(s_progtext, numRemoved, numTracks);
							getTracks();
						}
					});
				};
				getTracks = function() {
					$.ajax({
						url: "https://api.spotify.com/v1/playlists/" + s_id + "/tracks",
						async: true,
						cache: false,
						headers: {
							'Authorization': 'Bearer ' + accessToken
						},
						data: {
							"fields": "total,items.track.uri",
							"limit": 100,
							"offset": 0
						},
						type: "GET",
						timeout: 10000,
						error: function(a,b,c) { ajaxErr(a,b,c,getTracks,failureCallback); },
						success: function(response) {
							if (response.total == 0) {
								return successCallback(s_id);
							}
							if (numTracks == 0) {
								numTracks = response.total;
							}
							removeTracks(response)
						}
					});
				};
				getTracks();
			};

			window.createPlaylist = function(s_name, successCallback, failureCallback)
			{
				var s_progtext = "creating playlist \"" + s_name + "\"";
				setProgress(s_progtext, 0, 30);
				var createFunc = null;
				createFunc = function() {
					$.ajax({
						url: "https://api.spotify.com/v1/users/" + userID + "/playlists",
						async: true,
						cache: false,
						headers: {
							'Authorization': 'Bearer ' + accessToken,
							'Content-Type': 'application/json'
						},
						data: JSON.stringify({
							"name": s_name,
							"public": false,
							"collaborative": false,
							"description": "Playlist with random songs from my library. Generated by https://bbean.us/randolists."
						}),
						type: "POST",
						timeout: 10000,
						error: function(a,b,c) { ajaxErr(a,b,c,createFunc,failureCallback); },
						success: function(response) {
							successCallback(response.uri);
						}
					});
				};
				createFunc();
				setProgress(s_progtext, 15, 30);
			};

			window.populatePlaylist = function(s_name, s_id, i_cnt, i_librarySize, successCallback, failureCallback)
			{
				i_cnt = Math.min(i_cnt, i_librarySize);
				if (i_cnt == 0) {
					successCallback();
				}

				var s_progtext = "populating playlist \"" + s_name + "\"";
				setProgress(s_progtext, 0, i_cnt);
				var songsAdded = [];
				var songsPending = [];
				var getSongs = null;
				var addSongs = null;
				addSongs = function() {
					var uris = [];
					$.each(songsPending, function(k, o_song) {
						uris[uris.length] = o_song.uri;
					});
					$.ajax({
						url: "https://api.spotify.com/v1/playlists/" + s_id + "/tracks",
						async: true,
						cache: false,
						headers: {
							'Authorization': 'Bearer ' + accessToken,
							'Content-Type': 'application/json'
						},
						data: JSON.stringify({
							'uris': uris
						}),
						type: "POST",
						timeout: 10000,
						error: function(a,b,c) { ajaxErr(a,b,c,addSongs,failureCallback); },
						success: function(response) {
							$.each(songsPending, function(k, o_song) {
								songsAdded[songsAdded.length] = o_song.idx;
							});
							songsPending = [];
							if (songsAdded.length >= i_cnt)
							{
								return successCallback();
							}
							getSongs();
						}
					});
				};
				getSongs = function() {
					if (songsPending.length >= 5 || songsPending.length + songsAdded.length >= i_cnt)
					{
						return addSongs();
					}
					i_songIdx = Math.floor(Math.random() * i_librarySize);
					while (songsAdded.indexOf(i_songIdx) > -1 || songsPending.indexOf(i_songIdx) > -1)
					{
						i_songIdx = Math.floor(Math.random() * i_librarySize);
					}
					getTrack(i_songIdx, function(o_track) {
						songsPending[songsPending.length] = {
							'idx': i_songIdx,
							'uri': o_track.uri
						};
						setProgress(s_progtext, songsAdded.length + songsPending.length, i_cnt);
						getSongs();
					}, null);
				};
				getSongs();
			};

			window.buildPlaylist = function()
			{
				var status = "getting library size";
				var s_name = $("#optName").val();
				var i_count = $("#optSize").val();
				var b_replace = $("#optReplace").is(":checked");

				var failureCallback = function(xhr, ajaxOptions, thrownError) {
					alert("Error while " + status);
					setProgress("error", 30, 30);
				};

				getLibrarySize(function(i_librarySize) {
					status = "finding existing playlist";
					getPlaylist(s_name, function(b_found, s_playlistID) {
						var successCallback = function(s_playlistID) {
							status = "populating playlist with random songs";
							populatePlaylist(s_name, s_playlistID, i_count, i_librarySize, function() {
								setProgress("Done!", 30, 30);
							}, failureCallback);
						};
						if (b_found) {
							if (b_replace) {
								status = "clearing existing playlist";
								clearPlaylist(s_name, s_playlistID, successCallback, failureCallback);
							} else {
								alert("Error! Playlist \"" + s_name + "\" already exists!");
								setProgress("Done!", 30, 30);
							}
						} else {
							status = "creating playlist";
							createPlaylist(s_name, successCallback, failureCallback);
						}
					}, failureCallback);
				}, failureCallback);
			};
		</script>

		<div>

			<div id="login" style="margin: 0 auto; width: 400px;">
				<?php
				$s_redirect_login_uri = "https%3A%2F%2Fbbean.us%2Frandolists%2Findex.php";
				$s_scopes = urlencode(implode(" ", $as_scopes));
				$s_login_url = "https://accounts.spotify.com/authorize" . "?client_id={$s_api_id}" .
				               "&response_type=code" . "&redirect_uri={$s_redirect_login_uri}" .
				               "&scope={$s_scopes}" . "&state={$_SESSION['state']}";
				if (isset($_SESSION['show_dialog'])) $s_login_url .= "&show_dialog=true";
				?>
				<h1>Welcome to RandoLists!</h1>
				<span style="font-size:16px;">
					RandoLists will allow you to create a playlist with up to 10,000 random songs from your library.<br />
					To get started you need to <a href="<?php echo $s_login_url; ?>">Log in to Spotify</a>
					<?php echo $s_loginerr; ?>
				</span>
			</div>

			<div id="loggedin" style="text-align: center;">
				<div id="randoListsDiv">
					<h1>Welcome to RandoLists <span class="uname">[uname]</span>!</h1>
					<div style="width: 200px; height: 220px; margin: 0 auto; position: relative;">
						<div style="width: 200px; height: 200px; margin: 0 auto; background-position: center; background-repeat: no-repeat; border-radius: 200px; border: 2px solid white; position: absolute; background-color: #555;">
							<img class="uimage" id="avatar" style="visibility: hidden;" src="" />
						</div>
						<div style="width: 200px; height: 125px; background-color: rgba(50, 50, 50, 0.5); position: absolute; left: 2px; top: 2px; border-radius: 200px; text-align: center; padding-top: 75px; font-size: 31px;color: white; display: none; cursor: pointer;" onclick="logout();">Log out</div>
					</div>
					<div style="padding-bottom: 5px;">
						Create a playlist with up to 10,000 random songs from your library:
					</div>
					<div>
						<div style="padding-bottom: 5px;">
							Playlist Name: <input id="optName" type="text" value="RandoLists" />
							Size: <input id="optSize" type="number" min="1" max="10000" value="300" />
							Replace existing: <input id="optReplace" type="checkbox" checked />
							<input type="button" onclick="buildPlaylist()" value="Go!">
						</div>
						<div style="padding-bottom: 5px;">
							<span id="progressStatus" style="font-style: italic;">waiting...</span>
							<progress id="progressValue" value="30" max="30"></progress>
						</div>
					</div>
				</div>
				<div id="playerDiv" style="display: none;">
					<script type="text/javascript">
						<?php
							if ($b_loggedin) {
								echo "window.playerInterval = setInterval(drawCurrentlyPlaying, 5000); drawCurrentlyPlaying();";
							}
						?>
					</script>
					<img src="">
				</div>
			</div>

			<div id="switchScreen" style="display: <?php echo $b_loggedin ? 'inline-block' : 'none'?>; position: absolute; height: 100px; top: 0;">
				<div style="background-color: rgba(0,0,0,0.5); color: white; height:100px; width:50px; border-bottom-right-radius: 10px; border-top-right-radius: 10px; border:1px solid black; margin-left: -15px; cursor: pointer;" onclick="switchScreens();">
					<div style="transform: rotate(270deg); width: 84px; margin-top: 40px; margin-left: -20px;">Switch View</div>
				</div>
			</div>

			<div style="text-align: center; position: fixed; top: 100%; transform: translateY(-30px); width: 100%">
				More information at <a href="https://github.com/gladclef/RandoLists">https://github.com/gladclef/RandoLists</a>
			</div>
		</div>
	</body>
</html>
