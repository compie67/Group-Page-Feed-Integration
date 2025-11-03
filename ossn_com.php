<?php
/**
 * GroupPageFeed Component (OSSN 8.9 Final NL tuned v8)
 * ---------------------------------------------------
 * ✅ Toont groeps- en pagina-posts in de homefeed
 * ✅ Verbergt privé-groepen voor niet-leden
 * ✅ Laat eigenaren, leden en admins hun groepen wel zien
 * ✅ Geen core-wijzigingen
 */

define('GROUPPAGEFEED', ossn_route()->com . 'GroupPageFeed/');

function grouppagefeed_init() {
    error_log('[GROUPPAGEFEED] 🚀 Init gestart');

    // Hook: filter groepsposts vóór weergave
    ossn_add_hook('wall', 'load:post', 'grouppagefeed_filter_private_groups');

    error_log('[GROUPPAGEFEED] ✅ Hooks geregistreerd');
}
ossn_register_callback('ossn', 'init', 'grouppagefeed_init');

/**
 * 🧠 Filter: verberg privé-groepsposts voor niet-leden
 */
function grouppagefeed_filter_private_groups($hook, $type, $return, $params) {
    if (empty($return) || !is_array($return)) {
        return $return;
    }

    $user = ossn_loggedin_user();
    $filtered = [];

    foreach ($return as $post) {
        if ($post->type === 'group') {
            $owner_guid = (int) (is_array($post->owner_guid) ? reset($post->owner_guid) : $post->owner_guid);
            if ($owner_guid > 0) {
                $group = ossn_get_group_by_guid($owner_guid);
                if (!$group) {
                    continue;
                }

                // 🔍 Haal privacy/membership op
                $membership = '';
                if (isset($group->membership)) {
                    $membership = strtolower($group->membership);
                } elseif (isset($group->data->membership)) {
                    $membership = strtolower($group->data->membership);
                }

                // 🔒 Controleer alleen als groep echt private/closed is
                if (in_array($membership, ['private', 'closed'])) {
                    $is_owner  = ($user && $group->owner_guid == $user->guid);
                    $is_member = ($user && function_exists('ossn_is_group_member') && ossn_is_group_member($group->guid, $user->guid));
                    $is_admin  = ($user && method_exists($user, 'canModerate') && $user->canModerate());

                    if (!$is_owner && !$is_member && !$is_admin) {
                        error_log('[GROUPPAGEFEED] 🚫 Verborgen privébericht uit groep: ' . $group->title);
                        continue;
                    }
                }
            }
        }
        $filtered[] = $post;
    }

    return $filtered;
}
