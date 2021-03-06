<?php
/**
 * Kunena Component
 * @package Kunena.Framework
 * @subpackage Forum.Message.Thankyou
 *
 * @copyright (C) 2008 - 2012 Kunena Team. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link http://www.kunena.org
 **/
defined ( '_JEXEC' ) or die ();

/**
 * Kunena Forum Message Thank You Helper Class
 * @since 2.0
 */
abstract class KunenaForumMessageThankyouHelper {
	protected static $_instances = array();

	/**
	 * Returns KunenaForumMessageThankyou object
	 *
	 * @access	public
	 * @param	identifier		The message to load - Can be only an integer.
	 * @return	KunenaForumMessageThankyou		The thankyou object.
	 * @since	1.7
	 */
	static public function get($identifier, $reload = false) {
		if ($identifier instanceof KunenaForumMessageThankyou) {
			return $identifier;
		}
		$id = intval ( $identifier );
		if ($id < 1)
			return;

		if ($reload || empty ( self::$_instances [$id] )) {
			unset(self::$_instances [$id]);
			self::loadMessages ( array($id) );
		}

		return self::$_instances [$id];
	}

	/**
	 * Total number of Thank yous
	 * @param int $starttime Starting time as unix timestamp
	 * @param int $endtime Ending time as unix timestamp
	 * @return int
	 */
	static public function getTotal($starttime = null, $endtime = null) {
		$db = JFactory::getDBO();
		$where = array ();
		if (! empty ( $starttime ))
			$where [] = "time >= UNIX_TIMESTAMP({$db->quote(intval($starttime))})";
		if (! empty ( $endtime ))
			$where [] = "time <= UNIX_TIMESTAMP({$db->quote(intval($endtime))})";
		$query = "SELECT COUNT(*) FROM #__kunena_thankyou";
		if (! empty ( $where ))
			$query .= " WHERE " . implode ( " AND ", $where );
		$db->setQuery ( $query );
		$results = (int) $db->loadResult ();
		KunenaError::checkDatabaseError();

		return $results;
	}

	/**
	 * Get most thanked/thanking users
	 * @param bool $target
	 * @param int $limitstart
	 * @param int $limit
	 * @return List of users
	 */
	static public function getTopUsers($target=true, $limitstart=0, $limit=10) {
		$field = 'targetuserid';
		if (!$target)
			$field = 'userid';

		$db = JFactory::getDBO();
		$query = "SELECT s.userid, count(s.{$field}) AS countid, u.username
				FROM #__kunena_thankyou AS s
				INNER JOIN #__users AS u
				WHERE s.{$field}=u.id
				GROUP BY s.{$field}
				ORDER BY countid DESC";
		$db->setQuery ( $query, (int) $limitstart, (int) $limit );
		$results = (array) $db->loadObjectList ();
		KunenaError::checkDatabaseError();

		return $results;
	}

	/**
	 * Get most thanked messages
	 * @param int $limitstart
	 * @param int $limit
	 * @return List of messages
	 */
	static public function getTopMessages($limitstart=0, $limit=10) {
		$db = JFactory::getDBO();
		$categories = KunenaForumCategoryHelper::getCategories();
		$catlist = implode(',', array_keys($categories));
		$query = "SELECT s.postid, COUNT(*) AS countid, m.catid, m.thread, m.id, m.subject
				FROM #__kunena_thankyou AS s
				INNER JOIN #__kunena_messages AS m ON s.postid=m.id
				INNER JOIN #__kunena_topics AS tt ON m.thread=tt.id
				WHERE m.catid IN ({$catlist}) AND m.hold=0 AND tt.hold=0
				GROUP BY s.postid
				ORDER BY countid DESC";

		$db->setQuery ( $query, (int) $limitstart, (int) $limit );
		$results = (array) $db->loadObjectList ();
		KunenaError::checkDatabaseError();

		return $results;
	}


	/**
	 * Get messages where a user received (or said) Thank You
	 * @param int $userid
	 * @param bool $target
	 * @param int $limitstart
	 * @param int $limit
	 * @return Objectlist List of the wanted messages
	 */
	static public function getUserMessages($userid, $target=true, $limitstart=0, $limit=10) {
		$db = JFactory::getDBO();
		$field = 'targetuserid';
		if (!$target)
			$field = 'userid';
		$categories = KunenaForumCategoryHelper::getCategories();
		$catlist = implode(',', array_keys($categories));
		$query = "SELECT m.catid, m.thread, m.id
				FROM #__kunena_thankyou AS t
				INNER JOIN #__kunena_messages AS m ON m.id=t.postid
				INNER JOIN #__kunena_topics AS tt ON m.thread=tt.id
				WHERE m.catid IN ({$catlist}) AND m.hold=0 AND tt.hold=0 AND t.{$field}={$db->quote(intval($userid))}";
		$db->setQuery ( $query, (int) $limitstart, (int) $limit );
		$results = $db->loadObjectList ();
		KunenaError::checkDatabaseError();

		return $results;
	}

	/**
	 * Load users who have given thank you to listed messages
	 * @param array $ids List of message IDs
	 */
	static protected function loadMessages($ids) {
		foreach ($ids as $i=>$id) {
			$id = intval($id);
			if (!$id || isset(self::$_instances [$id]))
				unset($ids[$i]);
		}
		if (empty($ids))
			return;

		$idlist = implode(',', $ids);

		$db = JFactory::getDBO ();
		$query = "SELECT *
				FROM #__kunena_thankyou
				WHERE postid IN ({$idlist})";
		$db->setQuery ( $query );
		$results = (array) $db->loadObjectList ();
		KunenaError::checkDatabaseError();

		foreach ( $ids as $id ) {
			self::$_instances [$id] = new KunenaForumMessageThankyou ($id);
		}
		foreach ( $results as $result ) {
			self::$_instances [$result->postid]->_add($result->userid, $result->time);
		}
		unset ($results);
	}

	/**
	 * Perform the recount thankyous in kunena_users table
	 * @return bool true if succes
	 * @since 2.0
	 */
	static public function recount() {
		$db = JFactory::getDBO ();

		// Users who have no thank yous, set thankyou count to 0
		$query ="UPDATE #__kunena_users AS u
			LEFT JOIN #__kunena_thankyou AS t ON t.targetuserid = u.userid
			SET u.thankyou = 0
			WHERE t.targetuserid IS NULL";
		$db->setQuery($query);
		$db->query ();
		if (KunenaError::checkDatabaseError ())
			return false;
		$rows = $db->getAffectedRows ();

		// Update user thankyou count
		$query = "INSERT INTO #__kunena_users (userid, thankyou)
			SELECT targetuserid AS userid, COUNT(*) AS thankyou
			FROM #__kunena_thankyou
			GROUP BY targetuserid
			ON DUPLICATE KEY UPDATE thankyou=VALUES(thankyou)";
		$db->setQuery ($query);
		$db->query ();
		if (KunenaError::checkDatabaseError ())
			return false;
		$rows += $db->getAffectedRows ();

		return $rows;
	}

	/**
	 * Returns KunenaForumMessageThankyou object
	 *
	 * @access	public
	 * @param	ids		The message to load - Can be only an instance of KunenaForumMessage.
	 * @return	KunenaForumMessageThankyou		The thankyou object.
	 * @since	2.0-BETA2
	 */
	static public function getByMessage($ids = false) {
		if ($ids === false) {
			return self::$_instances;
		} elseif ( is_array($ids) ) {
			$ids2 = array();
			foreach ($ids as $id) {
				if ($id instanceof KunenaForumMessage) $id = $id->id;
				$ids2[(int)$id] = (int)$id;
			}
			$ids = $ids2;
		} else {
			$ids = array($ids);
		}

		self::loadMessages ( $ids );

		$list = array();
		foreach($ids as $id) {
			if ( !empty(self::$_instances [$id]) ) {
				$list[$id] =self::$_instances [$id];
			}
		}
		return $list;
	}
}
