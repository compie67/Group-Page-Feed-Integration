<?php
/**
 * GroupPageFeed Component (OSSN 8.9 Final - English Clean Version)
 * ---------------------------------------------------------------
 * Displays group and business page posts in the main home feed.
 * Hides posts from private/closed groups for non-members.
 * Group owners, members, and site admins can still view them.
 * No core modifications required.
 */

define('GROUPPAGEFEED', ossn_route()->com . 'GroupPageFeed/');

function grouppagefeed_init() {
    error_log('[GROUPPAGEFEED] Init started');

    // Hook: filter group posts before rendering
    ossn_add_hook('wall', 'load:post', 'grouppagefeed_filter_private_groups');

    error_log('[GROUPPAGEFEED] Hooks registered');
}
ossn_register_callback('ossn', 'init', 'grouppagefeed_init');

/**
 * Filter: hide private group posts for non-members
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

                // Retrieve membership/privacy setting
                $membership = '';
                if (isset($group->membership)) {
                    $membership = strtolower($group->membership);
                } elseif (isset($group->data->membership)) {
                    $membership = strtolower($group->data->membership);
                }

                // Check only if the group is private or closed
                if (in_array($membership, ['private', 'closed'])) {
                    $is_owner  = ($user && $group->owner_guid == $user->guid);
                    $is_member = ($user && function_exists('ossn_is_group_member') && ossn_is_group_member($group->guid, $user->guid));
                    $is_admin  = ($user && method_exists($user, 'canModerate') && $user->canModerate());

                    // Hide post for users who are not owner, member, or admin
                    if (!$is_owner && !$is_member && !$is_admin) {
                        error_log('[GROUPPAGEFEED] Hidden post from private group: ' . $group->title);
                        continue;
                    }
                }
            }
        }
        $filtered[] = $post;
    }

    return $filtered;
}
