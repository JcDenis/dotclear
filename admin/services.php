<?php
# -- BEGIN LICENSE BLOCK ---------------------------------------
#
# This file is part of Dotclear 2.
#
# Copyright (c) 2003-2011 Olivier Meunier & Association Dotclear
# Licensed under the GPL version 2.0 license.
# See LICENSE file or
# http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
#
# -- END LICENSE BLOCK -----------------------------------------

#if (isset($_GET['dcxd'])) {
#	$_COOKIE['dcxd'] = $_GET['dcxd'];
#}

require dirname(__FILE__).'/../inc/admin/prepend.php';

$core->rest->addFunction('getPostById',array('dcRestMethods','getPostById'));
$core->rest->addFunction('quickPost',array('dcRestMethods','quickPost'));
$core->rest->addFunction('validatePostMarkup',array('dcRestMethods','validatePostMarkup'));
$core->rest->addFunction('getMeta',array('dcRestMethods','getMeta'));
$core->rest->addFunction('delMeta',array('dcRestMethods','delMeta'));
$core->rest->addFunction('setPostMeta',array('dcRestMethods','setPostMeta'));
$core->rest->addFunction('searchMeta',array('dcRestMethods','searchMeta'));

$core->rest->serve();

/* Common REST methods */
class dcRestMethods
{
	public static function getPostById($core,$get)
	{
		if (empty($get['id'])) {
			throw new Exception('No post ID');
		}
		
		$params = array('post_id' => (integer) $get['id']);
		
		if (isset($get['post_type'])) {
			$params['post_type'] = $get['post_type'];
		}
		
		$posts = $core->blog->getPosts($params);
		
		if (count($posts) == 0) {
			throw new Exception('No post for this ID');
		}
		$post = $posts->current();
		
		$rsp = new xmlTag('post');
		$rsp->id = $post->post_id;
		
		$rsp->blog_id($post->blog_id);
		$rsp->user_id($post->user_id);
		$rsp->cat_id($post->cat_id);
		$rsp->post_dt($post->post_dt);
		$rsp->post_creadt($post->post_creadt);
		$rsp->post_upddt($post->post_upddt);
		$rsp->post_format($post->post_format);
		$rsp->post_url($post->post_url);
		$rsp->post_lang($post->post_lang);
		$rsp->post_title($post->post_title);
		$rsp->post_excerpt($post->post_excerpt);
		$rsp->post_excerpt_xhtml($post->post_excerpt_xhtml);
		$rsp->post_content($post->post_content);
		$rsp->post_content_xhtml($post->post_content_xhtml);
		$rsp->post_notes($post->post_notes);
		$rsp->post_status($post->post_status);
		$rsp->post_selected($post->post_selected);
		$rsp->user_name($post->user_name);
		$rsp->user_firstname($post->user_firstname);
		$rsp->user_displayname($post->user_displayname);
		$rsp->user_email($post->user_email);
		$rsp->user_url($post->user_url);
		$rsp->cat_title($post->cat_title);
		$rsp->cat_url($post->cat_url);
		
		$rsp->post_display_content($post->getContent(true));
		$rsp->post_display_excerpt($post->getExcerpt(true));
		
		$metaTag = new xmlTag('meta');
		if (($meta = @unserialize($post->post_meta)) !== false)
		{
			foreach ($meta as $K => $V)
			{
				foreach ($V as $v) {
					$metaTag->$K($v);
				}
			}
		}
		$rsp->post_meta($metaTag);
		
		return $rsp;
	}
	
	
	public static function quickPost($core,$get,$post)
	{
		$cur = $core->con->openCursor($core->prefix.'post');
		
		$cur->post_title = !empty($post['post_title']) ? $post['post_title'] : '';
		$cur->user_id = $core->auth->userID();
		$cur->post_content = !empty($post['post_content']) ? $post['post_content'] : '';
		$cur->cat_id = !empty($post['cat_id']) ? (integer) $post['cat_id'] : null;
		$cur->post_format = !empty($post['post_format']) ? $post['post_format'] : 'xhtml';
		$cur->post_lang = !empty($post['post_lang']) ? $post['post_lang'] : '';
		$cur->post_status = !empty($post['post_status']) ? (integer) $post['post_status'] : 0;
		
		# --BEHAVIOR-- adminBeforePostCreate
		$core->callBehavior('adminBeforePostCreate',$cur);
		
		$return_id = $core->blog->addPost($cur);
		
		# --BEHAVIOR-- adminAfterPostCreate
		$core->callBehavior('adminAfterPostCreate',$cur,$return_id);
		
		$rsp = new xmlTag('post');
		$rsp->id = $return_id;
		
		$post = $core->blog->getPosts(array('post_id' => $return_id));
		
		$rsp->post_status = $post->post_status;
		$rsp->post_url = $post->getURL();
		return $rsp;
	}
	
	public static function validatePostMarkup($core,$get,$post)
	{
		if (!isset($post['excerpt'])) {
			throw new Exception('No entry excerpt');
		}
		
		if (!isset($post['content'])) {
			throw new Exception('No entry content');
		}
		
		if (empty($post['format'])) {
			throw new Exception('No entry format');
		}
		
		if (!isset($post['lang'])) {
			throw new Exception('No entry lang');
		}
		
		$excerpt = $post['excerpt'];
		$excerpt_xhtml = '';
		$content = $post['content'];
		$content_xhtml = '';
		$format = $post['format'];
		$lang = $post['lang'];
		
		$core->blog->setPostContent(0,$format,$lang,$excerpt,$excerpt_xhtml,$content,$content_xhtml);
		
		$rsp = new xmlTag('result');
		
		$v = htmlValidator::validate($excerpt_xhtml.$content_xhtml);
		
		$rsp->valid($v['valid']);
		$rsp->errors($v['errors']);
		
		return $rsp;
	}
	
	public static function getMeta($core,$get)
	{
		$postid = !empty($get['postId']) ? $get['postId'] : null;
		$limit = !empty($get['limit']) ? $get['limit'] : null;
		$metaId = !empty($get['metaId']) ? $get['metaId'] : null;
		$metaType = !empty($get['metaType']) ? $get['metaType'] : null;
		
		$sortby = !empty($get['sortby']) ? $get['sortby'] : 'meta_type,asc';
		
		$rs = $core->meta->getMetadata(array(
			'meta_type' => $metaType,
			'limit' => $limit,
			'meta_id' => $metaId,
			'post_id' => $postid));
		$stats = $core->meta->computeMetaStats($rs);
		
		$sortby = explode(',',$sortby);
		$sort = $sortby[0];
		$order = isset($sortby[1]) ? $sortby[1] : 'asc';
		
		switch ($sort) {
			case 'metaId':
				$sort = 'meta_id_lower';
				break;
			case 'count':
				$sort = 'count';
				break;
			case 'metaType':
				$sort = 'meta_type';
				break;
			default:
				$sort = 'meta_type';
		}
		
		$stats->sort($sort,$order);
		
		$rsp = new xmlTag();
		
		foreach ($stats as $meta)
		{
			$metaTag = new xmlTag('meta');
			$metaTag->type = $meta->meta_type;
			$metaTag->uri = rawurlencode($meta->meta_id);
			$metaTag->count = $meta->count;
			$metaTag->percent = $meta->percent;
			$metaTag->roundpercent = $meta->roundpercent;
			$metaTag->CDATA($meta->meta_id);
			
			$rsp->insertNode($metaTag);
		}
		
		return $rsp;
	}
	
	public static function setPostMeta($core,$get,$post)
	{
		if (empty($post['postId'])) {
			throw new Exception('No post ID');
		}
		
		if (empty($post['meta']) && $post['meta'] != '0') {
			throw new Exception('No meta');
		}
		
		if (empty($post['metaType'])) {
			throw new Exception('No meta type');
		}
		
		# Get previous meta for post
		$post_meta = $core->meta->getMetadata(array(
			'meta_type' => $post['metaType'],
			'post_id' => $post['postId']));
		$pm = array();
		foreach ($post_meta as $meta) {
			$pm[] = $meta->meta_id;
		}
		
		foreach ($core->meta->splitMetaValues($post['meta']) as $m)
		{
			if (!in_array($m,$pm)) {
				$core->meta->setPostMeta($post['postId'],$post['metaType'],$m);
			}
		}
		
		return true;
	}
	
	public static function delMeta($core,$get,$post)
	{
		if (empty($post['postId'])) {
			throw new Exception('No post ID');
		}
		
		if (empty($post['metaId']) && $post['metaId'] != '0') {
			throw new Exception('No meta ID');
		}
		
		if (empty($post['metaType'])) {
			throw new Exception('No meta type');
		}
		
		$core->meta->delPostMeta($post['postId'],$post['metaType'],$post['metaId']);
		
		return true;
	}
	
	public static function searchMeta($core,$get)
	{
		$q = !empty($get['q']) ? $get['q'] : null;
		$metaType = !empty($get['metaType']) ? $get['metaType'] : null;
		
		$sortby = !empty($get['sortby']) ? $get['sortby'] : 'meta_type,asc';
		
		$rs = $core->meta->getMetadata(array('meta_type' => $metaType));
		$stats = $core->meta->computeMetaStats($rs);
		
		$sortby = explode(',',$sortby);
		$sort = $sortby[0];
		$order = isset($sortby[1]) ? $sortby[1] : 'asc';
		
		switch ($sort) {
			case 'metaId':
				$sort = 'meta_id_lower';
				break;
			case 'count':
				$sort = 'count';
				break;
			case 'metaType':
				$sort = 'meta_type';
				break;
			default:
				$sort = 'meta_type';
		}
		
		$stats->sort($sort,$order);
		
		$rsp = new xmlTag();
		
		foreach ($stats as $meta)
		{
			if (preg_match('/'.$q.'/i',$meta->meta_id)) {
				$metaTag = new xmlTag('meta');
				$metaTag->type = $meta->meta_type;
				$metaTag->uri = rawurlencode($meta->meta_id);
				$metaTag->count = $meta->count;
				$metaTag->percent = $meta->percent;
				$metaTag->roundpercent = $meta->roundpercent;
				$metaTag->CDATA($meta->meta_id);
				
				$rsp->insertNode($metaTag);
			}
		}
		
		return $rsp;
	}
}
?>