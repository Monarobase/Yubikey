<?php
 /*
 * This file is part of Monarobase-Yubikey
 *
 * (c) 2013 Monarobase
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 *
 * @author    Monarobase
 * @package     Yubikey
 * @copyright   (c) 2013 Monarobase <jonathan@monarobase.net>
 * @link        http://monarobase.net
 */

namespace Monarobase\Yubikey;

use Illuminate\Support\Facades\Facade;

class YubikeyFacade extends Facade {

	/**
	 * Get the registered name of the component.
	 *
	 * @return string
	 */
	protected static function getFacadeAccessor() { return 'yubikey'; }

}