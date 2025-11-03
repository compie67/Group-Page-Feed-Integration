<?php
/**
 * Group + Page Feed Override (OSSN 8.9 – v8.1 Final Privacy-Integrated)
 * --------------------------------------------------------------------
 * Extends the home feed to include group + business page posts.
 * Filters private/closed groups for non-members directly in view.
 */

$OssnWall = new OssnWall();

// Extend feed: include groups + business pages
$params = ['type' => ['user', 'group', 'businesspage']];

// Pagination & infinite scroll
if (isset($_GET['offset'])) {
    $params['offset'] = (int) $_GET['offset'];
}
if (isset($_GET['page_limit'])) {
    $params['page_limit'] = (int) $_GET['page_limit'];
}

$posts = $OssnWall->GetPosts($params);

if ($posts) {
    $user = ossn_loggedin_user();

    foreach ($posts as $post) {
        // 🔒 Filter private/closed group posts for non-members
        if ($post->type === 'group') {
            $group = ossn_get_group_by_guid($post->owner_guid);
            if ($group && $user) {
                // Determine group privacy
                $privacy = '';
                if (isset($group->data) && is_object($group->data) && isset($group->data->membership)) {
                    $privacy = strtolower(trim($group->data->membership));
                } elseif (isset($group->membership)) {
                    $privacy = strtolower(trim($group->membership));
                } elseif (isset($group->privacy)) {
                    $privacy = strtolower(trim($group->privacy));
                }

                if (empty($privacy)) {
                    $privacy = 'private';
                }

                // Skip post if not allowed
                if (in_array($privacy, ['private', 'closed', OSSN_PRIVATE])) {
                    $is_member = (function_exists('ossn_is_group_member') && ossn_is_group_member($group->guid, $user->guid));
                    $is_admin  = (method_exists($user, 'canModerate') && $user->canModerate());
                    if (!$is_member && !$is_admin) {
                        error_log('[GROUPPAGEFEED] 🚫 Hidden post from private group: ' . $group->title);
                        continue;
                    }
                }
            }
        }

        //  Visible posts only below this line
        $item  = ossn_wallpost_to_item($post);
        $label = '';

        //  Group label
        if ($post->type === 'group') {
            $group = ossn_get_group_by_guid($post->owner_guid);
            if ($group) {
                $label = "<div style='font-size:12px;color:#777;margin-bottom:4px;'>
                            📌 Post from group:
                            <a href='" . ossn_site_url("group/{$group->guid}") . "'
                               style='color:#777;font-weight:bold;'>{$group->title}</a>
                          </div>";
            }
        }

        //  Business page label
        if ($post->type === 'businesspage') {
            if (class_exists('\Ossn\Component\BusinessPage\Page')) {
                $page_obj = new \Ossn\Component\BusinessPage\Page();
            } elseif (class_exists('OssnBusinessPage')) {
                $page_obj = new OssnBusinessPage();
            } else {
                $page_obj = null;
            }

            if ($page_obj) {
                $page = ossn_get_object($post->poster_guid);
                if ($page && isset($page->title)) {
                    $label = "<div style='font-size:12px;color:#777;margin-bottom:4px;'>
                                📄 Post from page:
                                <a href='" . ossn_site_url("page/view/{$page->guid}") . "'
                                   style='color:#777;font-weight:bold;'>{$page->title}</a>
                              </div>";
                }
            }
        }

        // Merge label above post text
        if ($label) {
            $item['text'] = $label . $item['text'];
        }

        echo ossn_wall_view_template($item);
    }

    // Pagination only on non-AJAX
    if (!ossn_is_xhr()) {
        $count = $OssnWall->GetPosts(array_merge($params, ['count' => true]));
        echo ossn_view_pagination($count);
    }
} else {
    echo '<div class="ossn-no-posts">' . ossn_print('ossn:wall:no:post') . '</div>';
}
