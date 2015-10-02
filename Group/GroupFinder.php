<?php
/**
 * @link https://github.com/bitrix-expert/tools
 * @copyright Copyright © 2015 Nik Samokhvalov
 * @license MIT
 */

namespace Bex\Tools\Group;

use Bex\Tools\Finder;
use Bitrix\Main\Application;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\ArgumentNullException;
use Bitrix\Main\GroupTable;

/**
 * @author Nik Samokhvalov <nik@samokhvalov.info>
 */
class GroupFinder extends Finder
{
    protected static $cacheTime = 8600000;
    protected static $cacheDir = 'bex_tools/groups';
    protected static $cacheTag = 'bex_user_groups';
    protected $id;
    protected $code;

    public function __construct(array $filter)
    {
        $filter = $this->prepareFilter($filter);

        if (isset($filter['id']))
        {
            $this->id = $filter['id'];
        }

        if (isset($filter['code']))
        {
            $this->code = $filter['code'];
        }

        if (!isset($this->id))
        {
            if (!isset($this->code))
            {
                throw new ArgumentNullException('code');
            }

            $this->id = $this->getFromCache([
                'type' => 'id'
            ]);
        }
    }

    public function id()
    {
        return $this->getFromCache([
            'type' => 'id'
        ]);
    }

    public function code()
    {
        return $this->getFromCache([
            'type' => 'code'
        ]);
    }

    protected function prepareFilter(array $filter)
    {
        foreach ($filter as $code => &$value)
        {
            if ($code === 'id')
            {
                intval($value);

                if ($value <= 0)
                {
                    throw new ArgumentNullException($code);
                }
            }
            else
            {
                trim(htmlspecialchars($value));

                if (strlen($value) <= 0)
                {
                    throw new ArgumentNullException($code);
                }
            }
        }

        return $filter;
    }

    protected function getValue(array $cache, array $filter)
    {
        switch ($filter['type'])
        {
            case 'id':
                $value = (int) $cache['GROUPS'][$this->code];

                if ($value <= 0)
                {
                    throw new ArgumentException('Group ID by group code "' . $this->code . '" not found');
                }
                break;

            case 'code':
                $value = $cache['CODES'][$this->id];

                if (strlen($value) <= 0)
                {
                    throw new ArgumentException('Group code by ID "' . $this->id . '" not found');
                }
                break;

            default:
                throw new \InvalidArgumentException('Invalid type of filter');
                break;
        }

        return $value;
    }

    protected function getItems()
    {
        $items = [];

        $rsGroups = GroupTable::getList();

        while ($group = $rsGroups->Fetch())
        {
            if ($group['STRING_ID'])
            {
                $items['GROUPS'][$group['STRING_ID']] = $group['ID'];
                $items['CODES'][$group['ID']] = $group['STRING_ID'];
            }
        }

        if (!empty($items))
        {
            Application::getInstance()->getTaggedCache()->registerTag($this->getCacheTag());
        }

        return $items;
    }

    public function getCacheTag()
    {
        return static::$cacheTag;
    }

    public function setCacheTag($tag)
    {
        static::$cacheTag = $tag;
    }

    /**
     * Handler after user group delete event
     *
     * @param integer $id Group ID
     */
    public static function onGroupDelete($id)
    {
        static::deleteGroupCache();
    }

    /**
     * Handler after user group add event
     *
     * @param array $fields Group fields
     */
    public static function onAfterGroupAdd(&$fields)
    {
        static::deleteGroupCache();
    }

    /**
     * Handler after user group update event
     *
     * @param integer $id Group ID
     * @param array $fields Group fields
     */
    public static function onAfterGroupUpdate($id, &$fields)
    {
        static::deleteGroupCache();
    }

    /**
     * Clears user group cache by tag
     */
    protected static function deleteGroupCache()
    {
        global $CACHE_MANAGER;

        if (defined('BX_COMP_MANAGED_CACHE'))
        {
            $CACHE_MANAGER->ClearByTag(static::$cacheTag);
        }
    }
}