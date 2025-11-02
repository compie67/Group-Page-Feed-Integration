<?php
/**
 * Group + Page Feed Override (OSSN 8.9 — v7.6)
 * ---------------------------------------------
 * Breidt de homefeed uit met groeps- en businesspage-posts.
 * Toont labels en respecteert groepsprivacy.
 */

$OssnWall = new OssnWall();
$user     = ossn_loggedin_user();

// 🔹 Basisquery: toon user-, group- en businesspage-posts
$params = array(
    'type' => array('user', 'group', 'businesspage'),
);

// Infinite scroll ondersteuning
if (isset($_GET['offset'])) {
    $params['offset'] = (int) $_GET['offset'];
}
if (isset($_GET['page_limit'])) {
    $params['page_limit'] = (int) $_GET['page_limit'];
}

$posts = $OssnWall->GetPosts($params);

if ($posts) {
    foreach ($posts as $post) {
        $item  = ossn_wallpost_to_item($post);
        $label = '';

        /**
         * 🏷️ GROEPS-POSTS
         * Toon label indien zichtbaar (filter al toegepast in hook)
         */
        if ($post->type === 'group') {
            $group = ossn_get_group_by_guid($post->owner_guid);
            if ($group) {
                $label = "<div style='font-size:12px;color:#777;margin-bottom:4px;'>
                            📌 Bericht uit groep:
                            <a href='" . ossn_site_url("group/{$group->guid}") . "'
                            style='color:#777;font-weight:bold;'>{$group->title}</a>
                          </div>";
            }
        }

        /**
         * 📄 PAGINA-POSTS
         * Toon paginalabel
         */
        if ($post->type === 'businesspage') {
            if (class_exists('OssnBusinessPage')) {
                $page_obj = new OssnBusinessPage();
                $page     = $page_obj->getByUsername($post->poster_guid);
            } elseif (class_exists('\Ossn\Component\BusinessPage\Page')) {
                $page_obj = new \Ossn\Component\BusinessPage\Page();
                $page     = $page_obj->getByUsername($post->poster_guid);
            } else {
                $page = null;
            }

            if ($page && isset($page->title)) {
                $label = "<div style='font-size:12px;color:#777;margin-bottom:4px;'>
                            📄 Bericht van pagina:
                            <a href='" . $page->getURL() . "'
                            style='color:#777;font-weight:bold;'>{$page->title}</a>
                          </div>";
            }
        }

        // Voeg label toe bovenaan de tekst
        if ($label) {
            $item['text'] = $label . $item['text'];
        }

        echo ossn_wall_view_template($item);
    }

    // Toon paginatie alleen bij niet-AJAX-aanroep
    if (!ossn_is_xhr()) {
        $count = $OssnWall->GetPosts(array_merge($params, array('count' => true)));
        echo ossn_view_pagination($count);
    }
} else {
    echo '<div class="ossn-no-posts">' . ossn_print('ossn:wall:no:post') . '</div>';
}
