<?php

/**
 * WordPress eXtended RSS file parser implementations
 *
 * @package WordPress
 * @subpackage Importer
 */

/**
 * WXR Parser that makes use of the SimpleXML PHP extension.
 */
class WXR_Parser_SimpleXML
{
    function parse($file)
    {
        $folders = $relations = array();

        $internal_errors = libxml_use_internal_errors(true);

        $dom = new DOMDocument;

        $old_value = null;

        if (function_exists('libxml_disable_entity_loader')) {
            $old_value = libxml_disable_entity_loader(true);
        }

        $success = $dom->loadXML(file_get_contents($file));

        if (!is_null($old_value)) {
            libxml_disable_entity_loader($old_value);
        }

        if (!$success || isset($dom->doctype)) {
            return new WP_Error('SimpleXML_parse_error', __('There was an error when reading this WXR file', 'wpfiles'), libxml_get_errors());
        }

        $xml = simplexml_import_dom($dom);

        unset($dom);

        // halt if loading produces an error
        if (!$xml)
            return new WP_Error('SimpleXML_parse_error', __('There was an error when reading this WXR file', 'wpfiles'), libxml_get_errors());

        $wxr_version = $xml->xpath('/rss/channel/wp:wxr_version');

        if (!$wxr_version)
            return new WP_Error('WXR_parse_error', __('This does not appear to be a WXR file, missing/invalid WXR version number', 'wpfiles'));

        $wxr_version = (string)trim($wxr_version[0]);

        // confirm that we are dealing with the correct file format
        if (!preg_match('/^\d+\.\d+$/', $wxr_version))
            return new WP_Error('WXR_parse_error', __('This does not appear to be a WXR file, missing/invalid WXR version number', 'wpfiles'));

        $base_url = $xml->xpath('/rss/channel/wp:base_site_url');

        $base_url = (string)trim(isset($base_url[0]) ? $base_url[0] : '');

        $namespaces = $xml->getDocNamespaces();

        if (!isset($namespaces['wp']))
            $namespaces['wp'] = 'http://wordpress.org/export/1.1/';
        if (!isset($namespaces['excerpt']))
            $namespaces['excerpt'] = 'http://wordpress.org/export/1.1/excerpt/';

        // grab folders
        foreach ($xml->channel->folder as $folder) {
            $folders[] = array(
                'id' => (int)$folder->id,
                'text' => (string)$folder->text,
                'parent' => (string)$folder->parent,
                'color' => (string)$folder->color,
                'starred' => (string)$folder->starred
            );
        }

        // grab folder relations
        foreach ($xml->channel->relation as $relation) {
            $relations[] = array(
                'folder_id' => (string)$relation->folder,
                'attachment_id' => (string)$relation->attachment
            );
        }

        return array(
            'folders' => $folders,
            'relations' => $relations,
            'base_url' => $base_url,
            'version' => $wxr_version
        );
        
    }
}
