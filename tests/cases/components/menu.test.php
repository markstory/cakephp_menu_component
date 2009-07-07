<?php
/**
 * Menu Component Test
 *
 * Copyright 2008, Mark Story.
 * 823 millwood rd. 
 * toronto, ontario M4G 1W3
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright 2008, Mark Story.
 * @link http://mark-story.com
 * @version 1.0
 * @author Mark Story <mark@mark-story.com>
 * @license http://www.opensource.org/licenses/mit-license.php The MIT License
 */
App::import('Component', array('Menu', 'Acl', 'Auth'));
App::import('Controller', 'AppController');

class TestMenuComponent extends MenuComponent {
	var $cacheKey = 'test_menu_storage';
	
	function testMergeMenu($cachedMenus) {
		return $this->_mergeMenuCache($cachedMenus);
	}
	
	function testFormatMenu($menus) {
		return $this->_formatMenu($menus);
	}
	
/**
 * Fake getControllers to reflect things in TestCase
 *
 * @return void
 **/
	function getControllers() {
		return array('Controller1', 'Controller2');
	}
	
}


class TestMenuController extends AppController {
	var $components = array('TestMenu');
}

class AuthUser extends CakeTestModel {
	var $name = 'AuthUser';
}

class Controller1Controller extends Controller {
	function action1() {}
	function action2() {}
}
class Controller2Controller extends Controller {
	function action1() {}
	function action2() {}
	function admin_action(){}
}

Mock::generate('AclComponent', 'MenuTestMockAclComponent');
/**
 * Menu Component Test Case
 *
 * @package default
 * @author Mark Story
 **/
class MenuComponentTestCase extends CakeTestCase {

/**
 * undocumented function
 *
 * @return void
 **/
	function setUp() {
		$this->Menu = new TestMenuComponent();
		$this->Controller = new TestMenuController();
		$this->Menu->Acl = new MenuTestMockAclComponent();
		$this->Menu->Auth = new AuthComponent();
		$this->_admin = Configure::read('Routing.admin');
		Configure::write('Routing.admin', 'admin');
	}

/**
 * initialize uses hidden features in components
 *
 * @return void
 **/
	function testInitialize() {
		$settings = array(
			'cacheKey' => 'my_test_key',
			'aclPath' => 'myAclPath/',
			'defaultMenuParent' => '__root__'
		);
		$this->Menu->initialize($this->Controller, $settings);
		$this->assertEqual($this->Menu->cacheKey, $settings['cacheKey']);
		$this->assertEqual($this->Menu->aclPath, $settings['aclPath']);
	}

/**
 * testWriteCache
 *
 * @access public
 * @return void
 */	
	function testWriteCache() {
		$this->Menu->generateRawMenus();
		$result = $this->Menu->writeCache();
		$this->assertTrue($result);
	}

/**
 * Test Add Menu Method
 *
 * @return void
 **/
	function testAddMenu() {
		$menu = array(
			'url' => array(
				'controller' => 'posts',
				'action' => 'delete'
			),
		);
		$this->Menu->addMenu($menu);
		$expected = array(
			'url' => array(
				'controller' => 'posts',
				'action' => 'delete',
			),
			'id' => 'posts-delete',
			'parent' => null,
			'title' => 'Delete',
			'weight' => 0
		);
		$this->assertEqual($this->Menu->rawMenus[0], $expected);
		$this->Menu->rawMenus = array();

		$menu = array(
			'url' => array(
				'controller' => 'posts',
				'action' => 'delete',
			),
			'id' => 'funky-id',
			'parent' => 'posts',
			'weight' => 0
		);
		$this->Menu->addMenu($menu);
		$expected = array(
			'url' => array(
				'controller' => 'posts',
				'action' => 'delete',
			),
			'id' => 'funky-id',
			'parent' => 'posts',
			'title' => 'Delete',
			'weight' => 0
		);
		$this->assertEqual($this->Menu->rawMenus[0], $expected);
		$this->Menu->rawMenus = array();
	}
/**
 * Test the merging of cached records and those add in beforeFilter()
 *
 * @access public
 * @return void
 */
	function testMergeMenus() {
		$cached = array(
			array(
				'parent' => null,
				'id' => 'comments',
				'title' => 'Comments',
				'url' => array(
					'controller' => 'comments',
					'action' => 'index',
					'admin' => false
				),
			),
			array(
				'parent' => null,
				'id' => 'comments-add',
				'title' => 'Add',
				'url' => array(
					'controller' => 'comments',
					'action' => 'add',
					'admin' => false
				),
			),
			array(
				'parent' => 'posts',
				'id' => 'posts-add',
				'title' => 'Add',
				'url' => array(
					'controller' => 'posts',
					'action' => 'add',
					'admin' => false,
				),
			),
			array(
				'parent' => null,
				'id' => 'posts',
				'title' => 'Posts',
				'url' => array(
					'controller' => 'posts',
					'action' => 'index',
					'admin' => false
				),
			),
		);
		$this->Menu->addMenu(array(
			'parent' => null,
			'title' => 'Dashboard',
			'url' => array('controller' => 'users', 'action' => 'home')
		));
		$result = $this->Menu->testMergeMenu($cached);
		$this->assertTrue(count($result), 5);
		$this->assertEqual($result[4]['title'], 'Dashboard');
		$this->Menu->rawMenus = array();
		
		
		$this->Menu->addMenu(array(
			'parent' => 'comments',
			'title' => 'Test Add',
			'url' => array(
				'controller' => 'comments',
				'action' => 'safety'
			),
		));
		$result = $this->Menu->testMergeMenu($cached);
		$this->assertEqual(count($result), 5);
		$this->assertEqual($result[4]['title'], 'Test Add');
	}
	
/**
 * undocumented function
 *
 * @return void
 **/
	function testFilterMethods() {
		$this->Menu->createExclusions();
		$methods = array('add', 'buildFoo', 'admin_view', 'bother');
		$result = $this->Menu->filterMethods($methods);
		$this->assertEqual($result, array('add', 'buildFoo', 'bother'));
		
		$methods[] = '__secret';
		$methods[] = '_hidden';
		$result = $this->Menu->filterMethods($methods);
		$this->assertEqual($result, array('add', 'buildFoo', 'bother'));
		
		$result = $this->Menu->filterMethods($methods, array('buildFoo'));
		$this->assertEqual($result, array('add', 'bother'));
	}
	
/**
 * test Menu Generation
 *
 * @return void
 **/
	function testConstructMenu() {
		$aro = array(
			'AuthUser' => array('id' => 1)
		);
		$this->Menu->Acl->setReturnValue('check', true);
		$this->Menu->Acl->expectCallCount('check', '7');
		
		$this->Menu->constructMenu($aro);
		
		$this->assertFalse(empty($this->Menu->menu));
		Cache::set(array('duration' => $this->Menu->cacheTime));
		$this->assertTrue(is_array(Cache::read('AuthUser1_'. $this->Menu->cacheKey)));
		$result = $this->Menu->menu;

		$this->assertEqual(count($result), 2);
		$this->assertEqual(count($result[0]['children']), 2);
		$this->assertEqual(count($result[1]['children']), 3);
		
		Cache::delete('AuthUser1_'.$this->Menu->cacheKey);
	}
	
	function testConstructMenuWithFails() {
		$aro = array(
			'AuthUser' => array('id' => 1)
		);
		$this->Menu->Acl->setReturnValue('check', true);
		$this->Menu->Acl->setReturnValueAt(2, 'check', false);
		$this->Menu->Acl->expectCallCount('check', '7');
		
		$this->Menu->constructMenu($aro);
		
		$this->assertFalse(empty($this->Menu->menu));
		Cache::set(array('duration' => $this->Menu->cacheTime));
		$this->assertTrue(is_array(Cache::read('AuthUser1_'. $this->Menu->cacheKey)));
		$result = $this->Menu->menu;
		$this->assertEqual(count($result), 1);
		$this->assertEqual(count($result[0]['children']), 3);
		
		Cache::delete('AuthUser1_'.$this->Menu->cacheKey);
	}
/**
 * testLoadCache
 *
 * @access public
 * @return void
 */	
	function testLoadCache() {
		$this->Menu->generateRawMenus();
		$this->Menu->writeCache();
		$this->Menu->rawMenus = array();
		
		$result = $this->Menu->loadCache();
		$this->assertTrue($result);
		$this->assertFalse(empty($this->Menu->rawMenus));
	}
/**
 * testClearCache
 *
 * @access public
 * @return void
 */
	function testClearCache() {
		$this->Menu->generateRawMenus();
		$this->Menu->writeCache();
		Cache::set(array('duration' => $this->Menu->cacheTime));
		$this->assertTrue(is_array(Cache::read($this->Menu->cacheKey)));
	}
/**
 * testformatMenu
 *
 * @return void
 **/
	function testFormatMenu() {
		$this->Menu->generateRawMenus();
		$result = $this->Menu->testFormatMenu($this->Menu->rawMenus);
		$this->assertEqual(count($result), 2); //2 controllers in test.
		$this->assertEqual(count($result[0]['children']), 2); // actions in first controller
		$this->assertEqual(count($result[1]['children']), 3); //actions second controller
		
		$expected = array(
			'id' => 'controller2-action1',
			'parent' => 'controller2',
			'url' => array(
				'controller' => 'controller2',
				'action' => 'action1',
				'admin' => false,
			),
			'title' => 'Action1',
			'weight' => 0,
			'children' => array(),
		);
		$this->assertEqual($result[1]['children'][0], $expected);
	}
/**
 * Generate the Raw Menus
 *
 * @return void
 **/
	function testGenerateRawMenus() {
		$this->Menu->generateRawMenus();
		$result = $this->Menu->rawMenus;
		$this->assertEqual(count($result), 7);
		
		$expected = array(
			'id' => 'controller1-action1',
			'parent' => 'controller1',
			'url' => array(
				'controller' => 'controller1',
				'action' => 'action1',
				'admin' => false,
			),
			'title' => 'Action1',
			'weight' => 0
		);
		$this->assertEqual($result[0], $expected);

		$expected = array(
			'id' => 'controller1',
			'parent' => '',
			'url' => array(
				'controller' => 'controller1',
				'action' => 'index',
				'admin' => false,
			),
			'title' => 'Controller1',
			'weight' => 0,
		);
		$this->assertEqual($result[2], $expected);
	}
/**
 * test Sorting by weight.
 *
 * @access public
 * @return void
 */	
	function testWeightSorting() {
		$this->Menu->Acl->setReturnValue('check', true);
		$this->Menu->addMenu(array(
			'title' => 'First',
			'url' => array(
				'controller' => 'posts',
				'action' => 'delete'
			),
			'weight' => 1,
		));
		$this->Menu->addMenu(array(
			'title' => 'Third',
			'url' => array(
				'controller' => 'posts',
				'action' => 'more_stuff'
			),
			'weight' => 4,
		));
		$this->Menu->addMenu(array(
			'title' => 'Second',
			'url' => array(
				'controller' => 'posts',
				'action' => 'do_stuff'
			),
			'weight' => 2,
		));
		$this->Menu->constructMenu(array('User' => array('id' => 1)));
		$result = $this->Menu->menu;
		$this->assertEqual($result[2]['title'], 'First');
		$this->assertEqual($result[3]['title'], 'Second');
		$this->assertEqual($result[4]['title'], 'Third');

		Cache::delete('User1_'.$this->Menu->cacheKey);
	}
	
	function tearDown() {
		ClassRegistry::flush();
		$this->Menu->clearCache();
		Configure::write('Routing.admin', $this->_admin);
		unset($this->Menu, $this->Controller);
	}
}
?>