<?php
/**
 * @package    Com_Api
 * @copyright  Copyright (C) 2009-2014 Techjoomla, Tekdi Technologies Pvt. Ltd. All rights reserved.
 * @license    GNU GPLv2 <http://www.gnu.org/licenses/old-licenses/gpl-2.0.html>
 * @link       http://www.techjoomla.com
 */
defined('_JEXEC') or die( 'Restricted access' );
require_once JPATH_SITE . '/components/com_content/models/articles.php';
require_once JPATH_SITE . '/components/com_content/models/article.php';
JLoader::register('FieldsHelper', JPATH_ADMINISTRATOR . '/components/com_fields/helpers/fields.php');

/**
 * Articles Resource
 *
 * @since  3.5
 */
class ArticlesApiResourceArticle extends ApiResource
{
    /**
     * get Method to get all artcle data
     *
     * @return  json
     *
     * @since  3.5
     */
    public function get()
    {
        $this->plugin->setResponse($this->getArticles());
    }

    /**
     * delete Method to delete article
     *
     * @return  json
     *
     * @since  3.5
     */
    public function delete()
    {
        $this->plugin->setResponse($this->deleteArticle());
    }

    /**
     * getArticles Method to getArticles data
     *
     * @return  array
     *
     * @since  3.5
     */
    public function getArticles()
    {
        $app = JFactory::getApplication();
        $items = array();
        $article_id = $app->input->get('id', 0, 'INT');
        $catid = $app->input->get('category_id', 0, 'INT');

        // Featured - hide,only,show
        $featured    = $app->input->get('featured', 0, 'INT');
        $created_by    = $app->input->get('created_by', 0, 'INT');
        $search = $app->input->get('search', '', 'STRING');
        $limitstart    = $app->input->get('limitstart', 0, 'INT');
        $limit    = $app->input->get('limit', 0, 'INT');

        $date_filtering    = $app->input->get('date_filtering', '', 'STRING');
        $start_date = $app->input->get('start_date_range', '', 'STRING');
        $end_date = $app->input->get('end_date_range', '', 'STRING');
        $realtive_date = $app->input->get('relative_date', '', 'STRING');

        $listOrder = $app->input->get('listOrder', 'ASC', 'STRING');

        $art_obj = new ContentModelArticles;

        $art_obj->setState('list.direction', $listOrder);

        if ($limit)
        {
            $art_obj->setState('list.start', $limitstart);
            $art_obj->setState('list.limit', $limit);
        }

        // Filter by category
        if ($catid)
        {
            $art_obj->setState('filter.category_id', $catid);
            $art_obj->setState('filter.subcategories', true);
            $art_obj->setState('filter.max_category_levels', 100);
        }

        if ($search)
        {
            $art_obj->setState('list.filter', $search);
        }

        // Filter by auther
        if ($created_by)
        {
            $art_obj->setState('filter.created_by', $created_by);
        }

        // Filter by featured
        if ($featured)
        {
            $art_obj->setState('filter.featured', $featured);
        }

        // Filter by article
        if ($article_id)
        {
            $art_obj->setState('filter.article_id', $article_id);
        }

        // Filtering
        if ($date_filtering)
        {
            $art_obj->setState('filter.date_filtering', $date_filtering);

            if ($date_filtering == 'range')
            {
                $art_obj->setState('filter.start_date_range', $start_date);
                $art_obj->setState('filter.end_date_range', $end_date);
            }
        }

        $rows = $art_obj->getItems();

        $num_articles = $art_obj->getTotal();
        $data = [];

        foreach ($rows as $subKey => $subArray)
        {
            $data[$subKey] = new \stdClass();
            $data[$subKey]->id = $subArray->id;
            $data[$subKey]->title = $subArray->title;
            $data[$subKey]->alias = $subArray->alias;
            $data[$subKey]->introtext = $subArray->introtext;
            $data[$subKey]->fulltext = $subArray->fulltext;
            $data[$subKey]->category = array('catid' => $subArray->catid, 'title' => $subArray->category_title);
            $data[$subKey]->state = $subArray->state;
            $data[$subKey]->created = $subArray->created;
            $data[$subKey]->modified = $subArray->modified;
            $data[$subKey]->publish_up = $subArray->publish_up;
            $data[$subKey]->publish_down = $subArray->publish_down;

            if ($subArray->images)
            {
                $images = json_decode($subArray->images);

                foreach ($images as $key => $value)
                {
                    if ($value)
                    {
                        $images->$key = JURI::base() . $value;
                    }
                }

                $data[$subKey]->images = $images;
            }

            $data[$subKey]->access = $subArray->access;
            $data[$subKey]->featured = $subArray->featured;
            $data[$subKey]->language = $subArray->language;
            $data[$subKey]->hits = $subArray->hits;

            if ($subArray->created_by)
            {
                $data[$subKey]->created_by = array('id' => $subArray->created_by, 'name' => $subArray->author);
            }

            $data[$subKey]->tags = $subArray->tags;
            $data[$subKey]->jcfields = [];

            $fields = FieldsHelper::getFields('com_content.article', $subArray, true);
            if( is_array($fields) ){
                foreach( $fields as $jcfield ){
                    $data[$subKey]->jcfields[$jcfield->name] = $jcfield->value;
                }
            }

        }

        return $data;
    }

    /**
     * Post is to create / update article
     *
     * @return  Boolean
     *
     * @since  3.5
     */
    public function post()
    {
        $this->plugin->setResponse($this->CreateUpdateArticle());
    }

    /**
     * CreateUpdateArticle is to create / upadte article
     *
     * @return  stdClass
     *
     * @since  3.5
     */
    public function CreateUpdateArticle()
    {
        if (version_compare(JVERSION, '3.0', 'lt')) {
            JTable::addIncludePath(JPATH_PLATFORM . 'joomla/database/table');
        }

        $obj = new stdclass;

        $app = JFactory::getApplication();
        $app->setLanguageFilter(false);
        $article_id = $app->input->get('id', 0, 'INT');

        if (!$article_id && empty($app->input->get('title', '', 'STRING'))) {
            $obj->success = false;
            $obj->message = 'Title is Missing';

            return $obj;
        }

        # if (!$article_id && empty($app->input->get('introtext', '', 'STRING'))) {
        #     $obj->success = false;
        #     $obj->message = 'Introtext is Missing';
        #
        #     return $obj;
        # }

        if (!$article_id && empty($app->input->get('catid', '', 'INT'))) {
            $obj->success = false;
            $obj->message = 'Category id is Missing';

            return $obj;
        }

        if ($article_id) {
            $article = JTable::getInstance('Content', 'JTable', array());
            $article->load($article_id);
            $data = array(
                'title' => $app->input->get('title', $article->title, 'STRING'),
                'alias' => $app->input->get('alias', $article->alias, 'STRING'),
                'introtext' => $app->input->get('introtext', $article->introtext, 'STRING'),
                'fulltext' => $app->input->get('fulltext', $article->fulltext, 'STRING'),
                'state' => $app->input->get('state', $article->state, 'INT'),
                'catid' => $app->input->get('catid', $article->catid, 'INT'),
                'publish_up' => $app->input->get('publish_up', $article->publish_up, 'STRING'),
                'publish_down' => $app->input->get('publish_down', $article->publish_down, 'STRING'),
                'language' => $app->input->post->get('language', $article->language, 'STRING'),
                'com_fields' => $app->input->get('com_fields', [], 'ARRAY' )
            );

            // Bind data
            if (!$article->bind($data))
            {
                $obj->success = false;
                $obj->message = $article->getError();

                return $obj;
            }
        }
        else
        {
            $data = array(
                'title' => $app->input->get('title', '', 'STRING'),
                'alias' => $app->input->get('alias', '', 'STRING'),
                'introtext' => $app->input->get('introtext', '', 'STRING'),
                'fulltext' => $app->input->get('fulltext', '', 'STRING'),
                'state' => $app->input->get('state', '', 'INT'),
                'catid' => $app->input->get('catid', '', 'INT'),
                'publish_up' => $app->input->get('publish_up', '', 'STRING'),
                'publish_down' => $app->input->get('publish_down', '', 'STRING'),
                'language' => $app->input->post->get('language', '*', 'STRING'),
                'com_fields' => $app->input->get('com_fields', [], 'ARRAY' )
            );
            $article = JTable::getInstance('content');
            $article->title = $data['title'];
            $article->alias = $data['alias'];
            $article->introtext = $app->input->get('introtext', '', 'STRING');
            $article->fulltext = $app->input->get('fulltext', '', 'STRING');
            $article->state = $data['state'];
            $article->catid = $data['catid'];
            $article->publish_up = $app->input->get('publish_up', '', 'STRING');
            $article->publish_down = $app->input->get('publish_down', '', 'STRING');
            $article->language = $data['language'];
            $article->com_fields = $app->input->get('com_fields', [], 'ARRAY' );
        }

        // Check the data.
        if (!$article->check())
        {
            $obj->success = false;
            $obj->message = $article->getError();

            return $obj;
        }

        $dispatcher = JEventDispatcher::getInstance();
        $dispatcher->trigger("onContentBeforeSave",array('com_content.article',$article, !!$article_id, $data ));
        // Store the data.
        if (!$article->store())
        {
            $obj->success = false;
            $obj->message = $article->getError();

            return $obj;
        }
        $dispatcher->trigger("onContentAfterSave",array('com_content.article',$article, !!$article_id, $data ));

        $images = json_decode($article->images);

        foreach ($images as $key => $value)
        {
            if ($value)
            {
                $images->$key = JURI::base() . $value;
            }
        }

        $article->images = $images;
        $obj->success = true;
        $obj->data = $article;

        return $obj;
    }


    /**
     * Delete an article
     *
     * @return stdClass
     */
    public function deleteArticle()
    {
        if (version_compare(JVERSION, '3.0', 'lt')) {
            JTable::addIncludePath(JPATH_PLATFORM . 'joomla/database/table');
        }

        $app = JFactory::getApplication();
        $article_id = $app->input->get('id', 0, 'INT');
        if ($article_id) {
            $article = JTable::getInstance('Content', 'JTable', array());
            $article->delete($article_id);

            return (object)[ 'success' => true ];
        }

        return (object)[ 'success' => false, 'message' => 'No article id'];
    }
}
// vim:sw=4 ts=4 sts=4 et
