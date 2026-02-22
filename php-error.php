<?php
// Capture last PHP error (fatal or otherwise)
$error = error_get_last();

// Standard 503 headers
if (!headers_sent()) {
	header('HTTP/1.1 503 Service Temporarily Unavailable');
	header('Retry-After: 3600');
}
?>
<!DOCTYPE html>
<html lang="en-US" class="error503">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>503 Service Temporarily Unavailable</title>
</head>
<body>

<?php $wpSEOSettings = get_option( 'wpseo_titles' ); ?>

<div style="text-align:center; margin: 2em auto; max-width:800px;">
	<img style="max-width:100%" src="/wp-content/uploads/<?php echo $wpSEOSettings['company_logo_meta']['url'] ?>">

	<h1 style='font-family: Arial; line-height:1.4; margin-bottom: 2em;'>Our Site Is Experiencing Technical Difficulties…<br>We apologize for the inconvenience.</h1>

	<h2 style='font-family: Arial'>Please check back later, or feel free to contact us:</h2>

	<?php
	$ci = get_option('customer_info') ?: [];
	$phone = $ci['phone-link'] ?? '';
	$email = $ci['email'] ?? '';

	$phone ? print "<h3 style='font-family: Arial'>By Phone: $phone</h3>" : null;
	$email ? print "<h3 style='font-family: Arial'>By Email: <a href='mailto:$email'>$email</a></h3>" : null;

	// DEVICE, AGENT, SYSTEM
	$agent = htmlspecialchars($_SERVER['HTTP_USER_AGENT'] ?? '');

	$devices = [
		'iPhone'  => 'an iPhone',
		'iPad'    => 'an iPad',
		'Android' => 'an Android',
		'Mac'     => 'a Mac',
		'Windows' => 'a Windows PC',
	];

	$device = 'a device';

	foreach ($devices as $needle => $label) {
		if (str_contains($agent, $needle)) {
			$device = $label;
			break;
		}
	}

	$ip = htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? 'Unknown');
	$page = htmlspecialchars($_SERVER['REQUEST_URI'] ?? '');
	$ref = htmlspecialchars($_SERVER['HTTP_REFERER'] ?? '');

	// ERROR DISPLAY (your new requirement)
	if ($error  && current_user_can('manage_options')) {
		$errMsg  = htmlspecialchars($error['message'] ?? '');
		$errFile = htmlspecialchars($error['file'] ?? '');
		$errLine = htmlspecialchars($error['line'] ?? '');

		echo "<div style='margin-top:2em; padding:1em; background:#fee; border:1px solid #f00; text-align:left;'>
			<h3 style='text-align: center; font-size: 28px; color:#900;'>Debug Information</h3>
			<p><strong>Error:</strong> {$errMsg}</p>
			<p><strong>File:</strong> {$errFile}</p>
			<p><strong>Line:</strong> {$errLine}</p>
			<p><strong>Page:</strong> {$page}</p>
			<p><strong>Referrer:</strong> {$ref}</p>
			<p><strong>User Agent:</strong> {$agent}</p>
			<p><strong>IP Address:</strong> {$ip}</p>
		</div>";
	}

	// SEND EMAIL NOTICE
	$to = "glendon@battleplanwebdesign.com";
	$subject = "PHP Fatal Error: " . ($_SERVER['SERVER_NAME'] ?? '');
	$message = "Site: " . ($_SERVER['SERVER_NAME'] ?? '') . "<br>";

	if ($error) {
		$message .= "Error: {$error['message']}<br>";
		$message .= "File: {$error['file']}<br>";
		$message .= "Line: {$error['line']}<br>";
	}
	$message .= "Page: {$page}<br>";
	$message .= "Referrer: {$ref}<br>";
	$message .= "IP: {$ip}<br>";

	if (!get_transient('bp_503_error_sent')) {
		set_transient('bp_503_error_sent', true, 120); // 2 minutes
		wp_mail($to, $subject, $message, ['Content-Type: text/html']);
	}
	?>
</div>

</body>
</html>
