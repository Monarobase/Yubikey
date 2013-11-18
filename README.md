# Yubikey

Yubikey for Laravel 4

Increase the security of your forms with ease using a USB key

[Buy a Yubikey](https://store.yubico.com)

[Yubico API Key Generator](https://upgrade.yubico.com/getapikey/)


## Installation

Add `monarobase/yubikey` to `composer.json`.
```
"monarobase/yubikey": "dev-master"
```

Run `composer update` to pull down the latest version of Yubikey.

Now open up `app/config/app.php` and add the service provider to your `providers` array.
```php
'providers' => array(
	'Monarobase\Yubikey\YubikeyServiceProvider',
)
```

Now add the alias.
```php
'aliases' => array(
	'Yubikey' => 'Monarobase\Yubikey\YubikeyFacade',
)
```

You can easily integrate the Yubikey Verification into your authentication system in two steps :
- Add a field (eg `yubikey_identity`) in your user table
- now check your user with username/email + password + yubikey_identity


## Configuration

Run `php artisan config:publish monarobase/yubikey` and modify the config file with your own informations.


## Example

```php
try
{
	$yubikey_auth = Yubikey::verify(Input::get('otp'));
	$yubikey_params = Yubikey::getParameters();
	$yubikey_identity = Yubikey::getParameter('identity');
}
catch (Exception $e)
{
	$error = $e->getMessage();
}
```
