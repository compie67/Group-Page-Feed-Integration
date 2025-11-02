ummary: Group & Page Feed Privacy Filtering Issue (OSSN 8.9)
Context

We developed a component called GroupPageFeed for OSSN v8.9, designed to extend the home feed with:

Posts from groups (OssnGroups)

Posts from business pages (BusinessPage component)

Full privacy filtering — only show posts a user is allowed to see

The component uses ossn_add_hook('wall', 'load:post', ...) to filter posts after retrieval, without changing the core.

What Works

✅ Public group posts appear correctly on the homefeed
✅ Business page posts appear with a page label
✅ No errors, no infinite loops after v7.8 (fixed component init logic)
✅ Works across PHP 7.4 / 8.1 and OSSN 8.1 → 8.9

The Core Problem

Private group posts are still visible on the home feed to users who are not members of those groups.

Even though the filter logic checks:

if (in_array($privacy, ['private', 'closed', OSSN_PRIVATE])) {
    if (!ossn_is_group_member($group->guid, $user->guid)) continue;
}


…the $group->data->membership and $group->privacy values are inconsistent or missing.
This causes the core OssnWall::GetPosts() to return all group posts, regardless of privacy.

As a result:

Users outside the group see private posts (including text and images).

Clicking the post still shows the “Join group” screen — so the privacy UI works, but the post data is already exposed on the homefeed.

Filtering these posts via hook works only partially, since not all group objects expose a reliable privacy field.

Likely Root Causes

Inconsistent privacy fields

$group->data->membership → sometimes “public” or “closed”

$group->membership → sometimes “public” or “private”

$group->privacy → sometimes missing or legacy value

In some older installations, only an entity exists with key "membership" or "privacy".

No native privacy check in OssnWall::GetPosts()
The wall query returns all posts of type “group” without verifying the current user’s membership.

Late execution of wall:load:post hooks
Hooks execute after data retrieval — so the component can only hide items after fetching, not prevent them from loading in the first place.

GroupPinPost / BusinessPage conflicts
Some modules call group methods on plain OssnObject instances (isModerator()), which breaks if the post is rendered from outside the group page context.

Recommended Fixes (Core-Level)

Add a standard privacy field to groups, e.g. $group->access = public|private, guaranteed to be populated.

Extend OssnWall::GetPosts() to:

Skip posts from private groups for non-members

Or trigger a filter hook before executing the SQL query.

Optionally, provide a helper like ossn_group_is_visible($group_guid, $user_guid) to centralize visibility logic.

Ensure consistent class naming in the BusinessPage module (OssnBusinessPage vs \Ossn\Component\BusinessPage\Page).

Example Scenario

Group “Danswijk residents” (private/closed)

User A (not a member) logs in

User A’s homefeed shows:

📌 Post from group: Danswijk residents
"Meeting at 20:00 tonight"


→ Clicking the group shows the private page (locked),
but the content is already exposed on the main feed.

Expected Behavior

Private group posts should never appear on the home feed to non-members.
They should only be visible to:

Logged-in members of the group

Site admins or moderators

Conclusion

The GroupPageFeed component now integrates cleanly and efficiently with OSSN’s wall system,
but highlights a core-level limitation:
OssnWall::GetPosts() does not honor group privacy.
A core hook or SQL-level access filter would fix this permanently and remove the need for complex post-fetch filtering.
