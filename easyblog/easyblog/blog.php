<?php
defined('_JEXEC') or die( 'Restricted access' );
//error_reporting(E_ALL);
//ini_set('display_errors', 1);

jimport('joomla.user.user');
jimport( 'simpleschema.category' );
jimport( 'simpleschema.person' );
jimport( 'simpleschema.blog.post' );

require_once( JPATH_ROOT . '/components/com_easyblog/constants.php' );
require_once( EBLOG_HELPERS . DIRECTORY_SEPARATOR . 'date.php' );
require_once( EBLOG_HELPERS . DIRECTORY_SEPARATOR . 'helper.php' );
require_once( EBLOG_HELPERS . DIRECTORY_SEPARATOR . 'string.php' );
require_once( EBLOG_CLASSES . DIRECTORY_SEPARATOR . 'adsense.php' );

class EasyblogApiResourceBlog extends ApiResource
{

	public function __construct( &$ubject, $config = array()) {
		
		parent::__construct( $ubject, $config = array() );
		$easyblog 	= JPATH_ROOT . '/administrator/components/com_easyblog/easyblog.php';
		if (!JFile::exists($easyblog)) {
			$this->plugin->setResponse( $this->getErrorResponse(404, 'Easyblog not installed') );
			return;
		}
		require_once( JPATH_ROOT . '/components/com_easyblog/helpers/helper.php' );
	}

	public function post()
	{    	
			$input = JFactory::getApplication()->input;
			$blog = EasyBlogHelper::getTable( 'Blog', 'Table' );
			$post = $input->post->getArray(array());
			
			$blog->bind($post);
			$blog->created_by = $this->plugin->getUser()->id;
			
			if (!$blog->store()) {
				$this->plugin->setResponse( $this->getErrorResponse(404, $blog->getError()) );
				return;
			}
			
			$item = EasyBlogHelper::getHelper( 'SimpleSchema' )->mapPost($blog, '<p><br><pre><a><blockquote><strong><h2><h3><em><ul><ol><li>');
			$this->plugin->setResponse( $item );
   	   
	}
	
	public function get() {
		$input = JFactory::getApplication()->input;
		$model = EasyBlogHelper::getModel( 'Blog' );
		$config = EasyBlogHelper::getConfig();
		$id = $input->get('id', null, 'INT');

		// If we have an id try to fetch the user
		$blog 		= EasyBlogHelper::getTable( 'Blog' );
		$blog->load( $id );
		
		if (!$id) {
			$this->plugin->setResponse( $this->getErrorResponse(404, 'Blog id cannot be blank') );
			return;
		}
				
		if (!$blog->id) {
			$this->plugin->setResponse( $this->getErrorResponse(404, 'Blog not found') );
			return;
		}

		$item = EasyBlogHelper::getHelper( 'SimpleSchema' )->mapPost($blog, '<p><br><pre><a><blockquote><strong><h2><h3><em><ul><ol><li>');
		$this->plugin->setResponse( $item );
	}
	
}