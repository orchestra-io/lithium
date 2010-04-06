<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright     Copyright 2010, Union of Rad, Inc. (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace lithium\tests\cases\storage\session\adapter;

use \lithium\storage\session\adapter\Php;

class PhpTest extends \lithium\test\Unit {

	public function setUp() {
		$this->_session = isset($_SESSION) ? $_SESSION : array();
		$this->_destroySession();

		$this->Php = new Php();
		$this->_destroySession();

		/* Garbage collection */
		$this->_gc_divisor = ini_get('session.gc_divisor');
		ini_set('session.gc_divisor', '1');
	}

	public function tearDown() {
		$this->_destroySession();

		/* Revert to original garbage collection probability */
		ini_set('session.gc_divisor', $this->_gc_divisor);
		$_SESSION = $this->_session;
	}

	protected function _destroySession($name = null) {
		if (!$name) {
			$name = session_name();
		}
		$settings = session_get_cookie_params();
		setcookie(
			$name, '', time() - 1000, $settings['path'], $settings['domain'],
			$settings['secure'], $settings['httponly']
		);
		if (session_id()) {
			session_destroy();
		}
		$_SESSION = array();
	}

	public function testEnabled() {
		$php = $this->Php;
		$this->_destroySession(session_name());
		$this->assertFalse($php::enabled());
	}

	public function testInit() {
		$id = session_id();
		$this->assertTrue(empty($id));

		$result = ini_get('session.name');
		$this->assertEqual('li3', $result);

		$result = ini_get('session.cookie_lifetime');
		$this->assertEqual(strtotime('+1 day') - time(), (integer) $result);

		$result = ini_get('session.cookie_domain');
		$this->assertEqual('', $result);

		$result = ini_get('session.cookie_secure');
		$this->assertFalse($result);

		$result = ini_get('session.cookie_httponly');
		$this->assertFalse($result);

		$result = ini_get('session.save_path');
		$this->assertEqual('', $result);
	}

	public function testCustomConfiguration() {
		$config = array(
			'session.name' => 'awesome_name', 'session.cookie_lifetime' => 1200,
			'session.cookie_domain' => 'awesome.domain',
			'session.save_path' => LITHIUM_APP_PATH . '/resources/tmp/'
		);

		$adapter = new Php($config);

		$result = ini_get('session.name');
		$this->assertEqual($config['session.name'], $result);

		$result = ini_get('session.cookie_lifetime');
		$this->assertEqual($config['session.cookie_lifetime'], (integer) $result);

		$result = ini_get('session.cookie_domain');
		$this->assertEqual($config['session.cookie_domain'], $result);

		$result = ini_get('session.cookie_secure');
		$this->assertFalse($result);

		$result = ini_get('session.cookie_httponly');
		$this->assertFalse($result);

		$result = ini_get('session.save_path');
		$this->assertEqual($config['session.save_path'], $result);
	}

	public function testIsStarted() {
		$result = $this->Php->isStarted();
		$this->assertFalse($result);

		$this->Php->read();

		$result = $this->Php->isStarted();
		$this->assertTrue($result);

		$this->_destroySession(session_name());
		$result = $this->Php->isStarted();
		$this->assertFalse($result);
	}

	public function testIsStartedNoInit() {
		$this->_destroySession(session_name());

		$Php = new Php(array('init' => false));
		$result = $Php->isStarted();
		$this->assertFalse($result);

		$Php = new Php();
		$Php->read();
		$result = $Php->isStarted();
		$this->assertTrue($result);
	}

	public function testKey() {
		$result = $this->Php->key();
		$this->assertEqual(session_id(), $result);

		$this->_destroySession(session_name());
		$result = $this->Php->key();
		$this->assertNull($result);
	}

	public function testWrite() {
		$key = 'write-test';
		$value = 'value to be written';

		$closure = $this->Php->write($key, $value);
		$this->assertTrue(is_callable($closure));

		$params = compact('key', 'value');
		$result = $closure($this->Php, $params, null);

		$this->assertEqual($_SESSION[$key], $value);
	}

	public function testRead() {
		$this->Php->read();

		$key = 'read_test';
		$value = 'value to be read';

		$_SESSION[$key] = $value;

		$closure = $this->Php->read($key);
		$this->assertTrue(is_callable($closure));

		$params = compact('key');
		$result = $closure($this->Php, $params, null);

		$this->assertIdentical($value, $result);

		$key = 'non-existent';
		$closure = $this->Php->read($key);
		$this->assertTrue(is_callable($closure));

		$params = compact('key');
		$result = $closure($this->Php, $params, null);
		$this->assertNull($result);

		$closure = $this->Php->read();
		$this->assertTrue(is_callable($closure));

		$result = $closure($this->Php, array('key' => null), null);
		$expected = array('read_test' => 'value to be read');
		$this->assertEqual($expected, $result);
	}

	public function testCheck() {
		$this->Php->read();

		$key = 'read';
		$value = 'value to be read';
		$_SESSION[$key] = $value;

		$closure = $this->Php->check($key);
		$this->assertTrue(is_callable($closure));

		$params = compact('key');
		$result = $closure($this->Php, $params, null);
		$this->assertTrue($result);

		$key = 'does_not_exist';
		$closure = $this->Php->check($key);
		$this->assertTrue(is_callable($closure));

		$params = compact('key');
		$result = $closure($this->Php, $params, null);
		$this->assertFalse($result);
	}

	public function testDelete() {
		$this->Php->read();

		$key = 'delete_test';
		$value = 'value to be deleted';

		$_SESSION[$key] = $value;

		$closure = $this->Php->delete($key);
		$this->assertTrue(is_callable($closure));

		$params = compact('key');
		$result = $closure($this->Php, $params, null);

		$this->assertTrue($result);

		$key = 'non-existent';

		$closure = $this->Php->delete($key);
		$this->assertTrue(is_callable($closure));

		$params = compact('key');
		$result = $closure($this->Php, $params, null);

		$this->assertFalse($result);
	}
}

?>