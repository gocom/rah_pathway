<?php

/*
 * rah_pathway - Custom permlinks for Textpattern CMS
 * https://github.com/gocom/rah_pathway
 *
 * Copyright (C) 2019 Jukka Svahn
 *
 * This file is part of rah_pathway.
 *
 * rah_pathway is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation, version 2.
 *
 * rah_pathway is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with rah_pathway. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * The plugin class.
 */
final class Rah_Pathway
{
    /**
     * Used article field.
     *
     * @var string
     */
    private $field;

    /**
     * Requested URL.
     *
     * @var string
     */
    private $pageUrl;

    /**
     * Stores an array of the real request parameters.
     *
     * @var array
     */
    private $stash = [];

    /**
     * Permlink cache.
     *
     * @var array
     */
    private $permlinks = [];

    /**
     * Constructor.
     */
    public function __construct()
    {
        register_callback([$this, 'install'], 'plugin_lifecycle.rah_pathway', 'installed');
        register_callback([$this, 'uninstall'], 'plugin_lifecycle.rah_pathway', 'deleted');

        try {
            $this->setField(get_pref('rah_pathway_field'));
        } catch (\Exception $e) {
            return;
        }

        $this->pageUrl = trim(serverSet('REQUEST_URI'), '/');

        register_callback([$this, 'route'], 'pretext');
        register_callback([$this, 'setPermlink'], 'pretext_end');
        register_callback([$this, 'restore'], 'pretext_end');
        register_callback([$this, 'sanitizer'], 'sanitize_for_url');

        if (txpinterface == 'admin') {
            global $event;
            register_callback([$this, 'setPermlink'], $event, '', 1);
        }
    }

    /**
     * Installer.
     */
    public function install()
    {
        create_pref('rah_pathway_field', '', 'site', PREF_PLUGIN, 'Rah_Pathway::renderFieldOption', 181);
    }

    /**
     * Uninstaller.
     */
    public function uninstall()
    {
        remove_pref('rah_pathway_field');
    }

    /**
     * Sets the used field.
     *
     * @param  string $column Column name
     * @return $this
     */
    private function setField($column)
    {
        $field = preg_replace('/[^a-z0-9_]/i', '', $column);

        if (!$field) {
            throw new \InvalidArgumentException('Invalid field name.');
        }

        $this->field = $field;

        return $this;
    }

    /**
     * Routes requests to the article.
     */
    public function route()
    {
        foreach (['id'] as $name) {
            if (isset($_POST[$name])) {
                $this->stash[$name] = $_POST[$name];
            } elseif (isset($_GET[$name])) {
                $this->stash[$name] = $_GET[$name];
            } else {
                $this->stash[$name] = null;
            }
        }

        if (!$this->pageUrl) {
            return;
        }

        $id = safe_field('ID', 'textpattern', $this->field."='".doSlash($this->pageUrl)."' limit 1");

        if (!$id) {
            return;
        }

        $_POST['id'] = $_GET['id'] = $id;
    }

    /**
     * Restores GET and POST parameters.
     */
    public function restore()
    {
        foreach ($this->stash as $name => $value) {
            if ($value === null) {
                unset($_GET[$name], $_POST[$name]);
            } else {
                $_POST[$name] = $_GET[$name] = $value;
            }
        }
    }

    /**
     * Registers permlink handler.
     *
     * @return void
     */
    public function setPermlink()
    {
        global $prefs;
        $prefs['custom_url_func'] = [$this, 'permlink'];
    }

    /**
     * Sanitizer that allows freeform URLs.
     *
     * @param  string $ent  Event
     * @param  string $step Step
     * @param  string $url  URL
     * @return string
     */
    public function sanitizer($ent, $step, $url)
    {
        global $event;

        if ($this->field === 'url_title' && $event === 'article') {
            return $url;
        }

        return '';
    }

    /**
     * Gets a permlink from the given data.
     *
     * @param  array  $data The data
     * @return string|bool
     */
    public function permlink($data)
    {
        if (empty($data['thisid']) || empty($data['url_title'])) {
            return false;
        }

        $id = (int)$data['thisid'];

        if (isset($this->permlinks[$id])) {
            return $this->permlinks[$id];
        }

        if (isset($data[$this->field])) {
            $url = $data[$this->field];
        } else {
            $url = safe_field($this->field, 'textpattern', "ID='$id' limit 1");
        }

        if ($url) {
            $url = hu . $url;
        } else {
            $url = false;
        }

        $this->permlinks[$id] = $url;

        return $url;
    }

    /**
     * Renders input.
     *
     * @param  string $name      Field name
     * @param  string $selected  Current value
     * @return string HTML select field
     */
    public static function renderFieldOption($name, $selected)
    {
        $options = [];

        foreach (getCustomFields() as $number => $label) {
            $options['custom_' . intval($number)] = $label;
        }

        return selectInput($name, $options, $selected, '', '', $name);
    }
}
