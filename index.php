<?php

$api_key = parse_ini_file("secret.ini");
$s_api_id = $api_key['client_id'];
$s_api_secret = $api_key['client_secret'];
$as_scopes = ['user-library-read', 'playlist-modify-private'];

session_start();
$b_loggedin = FALSE;
$s_loginerr = "";
if (isset($_GET['logout']))
{
	unset($_SESSION['access_token']);
	unset($_SESSION['authcode']);
}
if (!isset($_SESSION['access_token']))
{
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
				$_SESSION['access_token'] = $a_token['access_token'];
				$b_loggedin = TRUE;
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
			window.accessToken = "<?php echo $_SESSION['access_token']; ?>";

			$(document).ready(function() {
				
				// set the height of the body to the height of the document or window
				var winheight = Math.max($(document).height(), $(window).height());
				if ($("body").height() < winheight)
				{
					$("body").css('min-height', winheight - 50);
				}

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
			});

			window.ajaxErr = function(xhr, ajaxOptions, thrownError) {
				if (parseInt(xhr.status) == 0 && thrownError) {
					if ((thrownError+"").indexOf("NETWORK_ERR") > -1) {
						alert("network error encountered");
					}
				}
				alert("Error sending request: ("+xhr.status+") "+thrownError + ". Try logging out and back in.");
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
					error: ajaxErr,
					success: function(response) {
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
			<?php echo (($b_loggedin) ? "$(document).ready(getUserInfo);" : ""); ?>

			window.logout = function()
			{
				window.location.replace("https://bbean.us/randolists/index.php?logout");
			}
		</script>

		<div>

			<div id="login" style="margin: 0 auto; width: 400px;">
				<?php
				$s_redirect_login_uri = "https%3A%2F%2Fbbean.us%2Frandolists%2Findex.php";
				$s_scopes = urlencode(implode(" ", $as_scopes));
				$s_login_url = "https://accounts.spotify.com/authorize" . "?client_id={$s_api_id}" .
				               "&response_type=code" . "&redirect_uri={$s_redirect_login_uri}" .
				               "&scope={$s_scopes}";
				?>
				<h1>Welcome to RandoLists!</h1>
				<span style="font-size:16px;">
					RandoLists will allow you to create a playlist with up to 10,000 random songs from your library.<br />
					To get started you need to <a href="<?php echo $s_login_url; ?>">Log in to Spotify</a>
					<?php echo $s_loginerr; ?>
				</span>
			</div>

			<div id="loggedin" style="margin: 0 auto; width: 800px; text-align: center;">
				<h1>Welcome to RandoLists <span class="uname">[uname]</span>!</h1>
				<div style="width: 200px; margin: 0 auto; position: relative;">
					<div style="width: 200px; height: 200px; margin: 0 auto; background-position: center; background-repeat: no-repeat; border-radius: 200px; border: 2px solid white; position: absolute; background-color: #555;">
						<img class="uimage" id="avatar" style="visibility: hidden;" src="" />
					</div>
					<div style="width: 200px; height: 125px; background-color: rgba(50, 50, 50, 0.5); position: absolute; left: 2px; top: 2px; border-radius: 200px; text-align: center; padding-top: 75px; font-size: 31px;color: white; display: none; cursor: pointer;" onclick="logout();">Log out</div>
				</div>
			</div>

			<div>
				More information at <a href="https://github.com/gladclef/RandoLists">https://github.com/gladclef/RandoLists</a>
			</div>
		</div>
	</body>
</html>
