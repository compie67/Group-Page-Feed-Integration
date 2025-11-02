<?php
/**
 * GroupPageFeed Component (OSSN 8.9 Final v7.8)
 * ---------------------------------------------
 * ✅ Toont groeps- en pagina-posts in de homefeed
 * ✅ Verbergt privé-groepen voor niet-leden
 * ✅ Geen loops of dubbele hooks
 * ✅ Volledig OSSN 8.x compatibel
 */

define('GROUPPAGEFEED', ossn_route()->com . 'GroupPageFeed/');
if (!defined('GROUPPAGEFEED_LOADED')) {
    define('GROUPPAGEFEED_LOADED', true);

    ossn_register_callback('ossn', 'init', function() {
        // Zorg dat dit maar één keer init draait
        static $initialized = false;
        if ($initialized) {
            return;
        }
        $initialized = true;

        error_log('[GROUPPAGEFEED] 🚀 Init gestart');

        // Wacht niet op "init:groups" (die hookt zichzelf weer terug)
        // => direct hooks registreren als functie bestaat
        if (function_exists('ossn_is_group_member')) {
            grouppagefeed_register_hooks();
        } else {
            // Registreer fallback zodat we niet in loop raken
            ossn_register_callback('ossn', 'init:groups', 'grouppagefeed_register_hooks');
        }
    });
}

/**
 * ✅ Registreer hooks en views
 */
function grouppagefeed_register_hooks() {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    error_log('[GROUPPAGEFEED] ✅ Hooks geregistreerd');

    // Filter privé-groepen bij ophalen van posts
    ossn_add_hook('wall', 'load:post', 'grouppagefeed_filter_private_groups');

    // Voeg onze view toe voor labels
    ossn_extend_view('wall/siteactivity', 'grouppagefeed/siteactivity');
}

/**
 * 🧠 Filter privé-groepsposts bij niet-leden
 */
function grouppagefeed_filter_private_groups($hook, $type, $return, $params) {
    if (empty($return) || !is_array($return)) {
        return $return;
    }

    $user = ossn_loggedin_user();
    $filtered = [];

    foreach ($return as $post) {
        if ($post->type === 'group') {
            $owner_guid = is_array($post->owner_guid) ? reset($post->owner_guid) : $post->owner_guid;
            $owner_guid = (int)$owner_guid;

            if ($owner_guid > 0) {
                $group = ossn_get_group_by_guid($owner_guid);
                if (!$group) {
                    continue;
                }

                // 🔍 Bepaal groepsprivacy robuust
                $privacy = '';
                if (isset($group->data) && is_object($group->data) && isset($group->data->membership)) {
                    $privacy = strtolower(trim($group->data->membership));
                } elseif (isset($group->membership)) {
                    $privacy = strtolower(trim($group->membership));
                } elseif (isset($group->privacy)) {
                    $privacy = strtolower(trim($group->privacy));
                } else {
                    $entity = ossn_get_entities(array(
                        'owner_guid' => $group->guid,
                        'type'       => 'group',
                        'limit'      => 1,
                    ));
                    if ($entity && isset($entity[0]->value)) {
                        $privacy = strtolower(trim($entity[0]->value));
                    }
                }

                if (empty($privacy)) {
                    $privacy = 'private';
                }

                // 🔒 Privé of gesloten groepen verbergen
                if (in_array($privacy, array('private', 'closed', OSSN_PRIVATE))) {
                    $is_member = false;
                    if (function_exists('ossn_is_group_member') && $user) {
                        $is_member = ossn_is_group_member($group->guid, $user->guid);
                    }
                    $is_admin = ($user && method_exists($user, 'canModerate') && $user->canModerate());

                    if (!$is_member && !$is_admin) {
                        error_log('[GROUPPAGEFEED] 🔒 Privé-groep verborgen: ' . $group->title);
                        continue;
                    }
                }
            }
        }
        $filtered[] = $post;
    }

    return $filtered;
}
