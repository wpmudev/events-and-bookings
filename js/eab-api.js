/* ----- Login with Fb/Tw ----- */
(function ($) {

function create_login_interface ($me) {
	if ($("#wpmudevevents-login_links-wrapper").length) {
		$("#wpmudevevents-login_links-wrapper").remove();
	}
	$me.parents('.wpmudevevents-buttons').after('<div id="wpmudevevents-login_links-wrapper" />');
	var $root = $("#wpmudevevents-login_links-wrapper");
	var post_id = $me.parents(".wpmudevevents-buttons").find('input:hidden[name="event_id"]').val();
	$root.html(
		'<ul class="wpmudevevents-login_links">' +
			'<li><a href="#" class="wpmudevevents-login_link wpmudevevents-login_link-facebook">' + l10nEabApi.facebook + '</a></li>' +
			'<li><a href="#" class="wpmudevevents-login_link wpmudevevents-login_link-twitter">' + l10nEabApi.twitter + '</a></li>' +
			'<li><a href="#" class="wpmudevevents-login_link wpmudevevents-login_link-wordpress">' + l10nEabApi.wordpress + '</a></li>' +
			'<li><a href="#" class="wpmudevevents-login_link wpmudevevents-login_link-cancel">' + l10nEabApi.cancel + '</a></li>' +
		'</ul>'
	);
	$me.addClass("active");
	$root.find(".wpmudevevents-login_link").each(function () {
		var $lnk = $(this);
		var callback = false;
		if ($lnk.is(".wpmudevevents-login_link-facebook")) {
			// Facebook login
			callback = function () {
				FB.login(function (resp) {
					if (resp.authResponse && resp.authResponse.userID) {
						// change UI
						$root.html('<img src="' + _eab_data.root_url + 'waiting.gif" /> ' + l10nEabApi.please_wait);
						$.post(_eab_data.ajax_url, {
							"action": "eab_facebook_login",
							"user_id": resp.authResponse.userID,
							"token": FB.getAccessToken()
						}, function (data) {
							var status = 0;
							try { status = parseInt(data.status); } catch (e) { status = 0; }
							if (!status) { // ... handle error
								$root.remove();
								$me.click();
								return false;
							}
							// Get form if all went well
							$.post(_eab_data.ajax_url, {
								"action": "eab_get_form",
								"post_id": post_id
							}, function (data) {
								$("body").append('<div id="eab-facebook_form">' + data + '</div>');
								$("#eab-facebook_form").find("." + $me.removeClass("active").attr("class")).click();
							});
						});
					}
				}, {scope: 'email'});
				return false;
			};
		} else if ($lnk.is(".wpmudevevents-login_link-twitter")) {
			callback = function () {
				var init_url = $.browser.opera ? '' : 'https://api.twitter.com/';
				var twLogin = window.open(init_url, "twitter_login", "scrollbars=no,resizable=no,toolbar=no,location=no,directories=no,status=no,menubar=no,copyhistory=no,height=400,width=600");
				$.post(_eab_data.ajax_url, {
					"action": "eab_get_twitter_auth_url",
					"url": window.location.toString()
				}, function (data) {
					try {
						twLogin.location = data.url;
					} catch (e) { twLogin.location.replace(data.url); }
					var tTimer = setInterval(function () {
						try {
							if (twLogin.location.hostname == window.location.hostname) {
								// We're back!
								var location = twLogin.location;
								var search = '';
								try { search = location.search; } catch (e) { search = ''; }
								clearInterval(tTimer);
								twLogin.close();
								// change UI
								$root.html('<img src="' + _eab_data.root_url + 'waiting.gif" /> ' + l10nEabApi.please_wait);
								$.post(_eab_data.ajax_url, {
									"action": "eab_twitter_login",
									"secret": data.secret,
									"data": search
								}, function (data) {
									var status = 0;
									try { status = parseInt(data.status); } catch (e) { status = 0; }
									if (!status) { // ... handle error
										$root.remove();
										$me.click();
										return false;
									}
									// Get form if all went well
									$.post(_eab_data.ajax_url, {
										"action": "eab_get_form",
										"post_id": post_id
									}, function (data) {
										$("body").append('<div id="eab-twitter_form">' + data + '</div>');
										$("#eab-twitter_form").find("." + $me.removeClass("active").attr("class")).click();
									});
								});
							}
						} catch (e) {}
					}, 300);
				})
				return false;
			};
		} else if ($lnk.is(".wpmudevevents-login_link-wordpress")) {
			// Pass on to wordpress login
			callback = function () {
				window.location = $me.attr("href");
				return false;
			};
		} else if ($lnk.is(".wpmudevevents-login_link-cancel")) {
			// Drop entire thing
			callback = function () {
				$me.removeClass("active");
				$root.remove();
				return false;
			};
		}
		if (callback) $lnk
			.unbind('click')
			.bind('click', callback)
		;
	});
}

// Init
$(function () {
	$(
		"a.wpmudevevents-yes-submit, " +
		"a.wpmudevevents-maybe-submit, " +
		"a.wpmudevevents-no-submit"
	)
		.css("float", "left")
		.unbind('click')
		.click(function () {
			$(
				"a.wpmudevevents-yes-submit, " +
				"a.wpmudevevents-maybe-submit, " +
				"a.wpmudevevents-no-submit"
			).removeClass("active");
			create_login_interface($(this));
			return false;
		})
	;
});
})(jQuery);