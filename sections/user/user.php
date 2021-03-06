<?php
if (empty($_GET['id']) || !is_number($_GET['id']) || (!empty($_GET['preview']) && !is_number($_GET['preview']))) {
    error(404);
}
$UserID = (int)$_GET['id'];
$Bonus = new \Gazelle\Bonus($DB, $Cache);

if (!empty($_POST)) {
    authorize();
    foreach (['action', 'flsubmit', 'fltype'] as $arg) {
        if (!isset($_POST[$arg])) {
            error(403);
        }
    }
    if ($_POST['action'] !== 'fltoken') {
        error(403);
    }
    if ($_POST['flsubmit'] !== 'Send') {
        error(403);
    }
    if (!preg_match('/^fl-(other-[1-4])$/', $_POST['fltype'], $match)) {
        error(403);
    }
    $FL_OTHER_tokens = $Bonus->purchaseTokenOther($LoggedUser['ID'], $UserID, $match[1], $LoggedUser);
}
$Preview = isset($_GET['preview']) ? $_GET['preview'] : 0;
if ($UserID == $LoggedUser['ID']) {
    $OwnProfile = true;
    if ($Preview == 1) {
        $OwnProfile = false;
        $ParanoiaString = $_GET['paranoia'];
        $CustomParanoia = explode(',', $ParanoiaString);
    }
    $FL_Items = [];
} else {
    $OwnProfile = false;
    //Don't allow any kind of previewing on others' profiles
    $Preview = 0;
    $FL_Items = $Bonus->getListOther(G::$LoggedUser['BonusPoints']);
}
$EnabledRewards = Donations::get_enabled_rewards($UserID);
$ProfileRewards = Donations::get_profile_rewards($UserID);
$FA_Key = null;

if (check_perms('users_mod')) { // Person viewing is a staff member
    $DB->prepared_query('
        SELECT
            um.Username,
            um.Email,
            ula.last_access,
            um.IP,
            p.Level AS Class,
            uls.Uploaded,
            uls.Downloaded,
            coalesce(ub.points, 0) as BonusPoints,
            um.RequiredRatio,
            um.Title,
            um.torrent_pass,
            um.Enabled,
            um.Paranoia,
            um.Invites,
            um.can_leech,
            um.Visible,
            i.JoinDate,
            i.Info,
            i.Avatar,
            i.AdminComment,
            i.Donor,
            i.Artist,
            i.Warned,
            i.SupportFor,
            i.RestrictedForums,
            i.PermittedForums,
            i.Inviter,
            inviter.Username,
            COUNT(posts.id) AS ForumPosts,
            i.RatioWatchEnds,
            i.RatioWatchDownload,
            i.DisableAvatar,
            i.DisableInvites,
            i.DisablePosting,
            i.DisablePoints,
            i.DisableForums,
            i.DisableTagging,
            i.DisableUpload,
            i.DisableWiki,
            i.DisablePM,
            i.DisableIRC,
            i.DisableRequests,
            um.FLTokens,
            um.2FA_Key,
            SHA1(i.AdminComment),
            i.InfoTitle,
            la.Type AS LockedAccount,
            CASE WHEN uhafl.UserID IS NULL THEN 1 ELSE 0 END AS AcceptFL,
            CASE WHEN uhaud.UserID IS NULL THEN 0 ELSE 1 END AS UnlimitedDownload
        FROM users_main AS um
        LEFT JOIN user_last_access AS ula ON (ula.user_id = um.ID)
        INNER JOIN users_leech_stats AS uls ON (uls.UserID = um.ID)
        INNER JOIN users_info AS i ON (i.UserID = um.ID)
        LEFT JOIN user_bonus AS ub ON (ub.user_id = um.ID)
        LEFT JOIN users_main AS inviter ON (i.Inviter = inviter.ID)
        LEFT JOIN permissions AS p ON (p.ID = um.PermissionID)
        LEFT JOIN forums_posts AS posts ON (posts.AuthorID = um.ID)
        LEFT JOIN locked_accounts AS la ON (la.UserID = um.ID)
        LEFT JOIN user_has_attr AS uhafl ON (uhafl.UserID = um.ID)
        LEFT JOIN user_attr as uafl ON (uafl.ID = uhafl.UserAttrID AND uafl.Name = ?)
        LEFT JOIN user_has_attr AS uhaud ON (uhaud.UserID = um.ID)
        LEFT JOIN user_attr as uaud ON (uaud.ID = uhaud.UserAttrID AND uaud.Name = ?)
        WHERE um.ID = ?
        GROUP BY AuthorID
        ', 'no-fl-gifts', 'unlimited-download', $UserID
    );

    if (!$DB->has_results()) { // If user doesn't exist
        header("Location: log.php?search=User+$UserID");
    }

    list($Username, $Email, $LastAccess, $IP, $Class, $Uploaded, $Downloaded,
    $BonusPoints, $RequiredRatio, $CustomTitle, $torrent_pass, $Enabled, $Paranoia,
    $Invites, $DisableLeech, $Visible, $JoinDate, $Info, $Avatar, $AdminComment, $Donor,
    $Artist, $Warned, $SupportFor, $RestrictedForums, $PermittedForums, $InviterID,
    $InviterName, $ForumPosts, $RatioWatchEnds, $RatioWatchDownload, $DisableAvatar,
    $DisableInvites, $DisablePosting, $DisablePoints, $DisableForums, $DisableTagging,
    $DisableUpload, $DisableWiki, $DisablePM, $DisableIRC, $DisableRequests, $FLTokens,
    $FA_Key, $CommentHash, $InfoTitle, $LockedAccount, $AcceptFL, $UnlimitedDownload)
        = $DB->next_record(MYSQLI_NUM, [9, 12]);
} else { // Person viewing is a normal user
    $DB->prepared_query('
        SELECT
            um.Username,
            um.Email,
            ula.last_access,
            um.IP,
            p.Level AS Class,
            uls.Uploaded,
            uls.Downloaded,
            coalesce(ub.points, 0) as BonusPoints,
            um.RequiredRatio,
            um.Enabled,
            um.Paranoia,
            um.Invites,
            um.Title,
            um.torrent_pass,
            um.can_leech,
            i.JoinDate,
            i.Info,
            i.Avatar,
            um.FLTokens,
            i.Donor,
            i.Warned,
            COUNT(posts.id) AS ForumPosts,
            i.Inviter,
            i.DisableInvites,
            inviter.username,
            i.InfoTitle,
            CASE WHEN uhafl.UserID IS NULL THEN 1 ELSE 0 END AS AcceptFL
        FROM users_main AS um
        LEFT JOIN user_last_access AS ula ON (ula.user_id = um.ID)
        INNER JOIN users_leech_stats AS uls ON (uls.UserID = um.ID)
        INNER JOIN users_info AS i ON (i.UserID = um.ID)
        LEFT JOIN user_bonus AS ub ON (ub.user_id = um.ID)
        LEFT JOIN permissions AS p ON (p.ID = um.PermissionID)
        LEFT JOIN users_main AS inviter ON (i.Inviter = inviter.ID)
        LEFT JOIN forums_posts AS posts ON (posts.AuthorID = um.ID)
        LEFT JOIN user_has_attr AS uhafl ON (uhafl.UserID = um.ID)
        LEFT JOIN user_attr as uafl ON (uafl.ID = uhafl.UserAttrID AND uafl.Name = ?)
        WHERE um.ID = ?
        GROUP BY AuthorID
        ', 'no-fl-gifts', $UserID
    );

    if (!$DB->has_results()) { // If user doesn't exist
        header("Location: log.php?search=User+$UserID");
    }

    list($Username, $Email, $LastAccess, $IP, $Class, $Uploaded, $Downloaded,
    $BonusPoints, $RequiredRatio, $Enabled, $Paranoia, $Invites, $CustomTitle,
    $torrent_pass, $DisableLeech, $JoinDate, $Info, $Avatar, $FLTokens, $Donor, $Warned,
    $ForumPosts, $InviterID, $DisableInvites, $InviterName, $InfoTitle, $AcceptFL)
        = $DB->next_record(MYSQLI_NUM, [10, 12]);
    $UnlimitedDownload = null;
}

$BonusPointsPerHour = $Bonus->userHourlyRate($UserID);

// Image proxy CTs
$DisplayCustomTitle = $CustomTitle;
if (check_perms('site_proxy_images') && !empty($CustomTitle)) {
    $DisplayCustomTitle = preg_replace_callback('~src=("?)(http.+?)(["\s>])~',
                                function($Matches) {
                                    return 'src=' . $Matches[1] . ImageTools::process($Matches[2]) . $Matches[3];
                                }, $CustomTitle);
}

if ($Preview == 1) {
    if (strlen($ParanoiaString) == 0) {
        $Paranoia = [];
    } else {
        $Paranoia = $CustomParanoia;
    }
} else {
    $Paranoia = unserialize($Paranoia);
    if (!is_array($Paranoia)) {
        $Paranoia = [];
    }
}
$ParanoiaLevel = 0;
foreach ($Paranoia as $P) {
    $ParanoiaLevel++;
    if (strpos($P, '+') !== false) {
        $ParanoiaLevel++;
    }
}

$JoinedDate = time_diff($JoinDate);

function check_paranoia_here($Setting) {
    global $Paranoia, $Class, $UserID, $Preview;
    if ($Preview == 1) {
        return check_paranoia($Setting, $Paranoia, $Class);
    } else {
        return check_paranoia($Setting, $Paranoia, $Class, $UserID);
    }
}

View::show_header($Username, "jquery.imagesloaded,jquery.wookmark,user,bbcode,requests,lastfm,comments,info_paster", "tiles");
$User = new \Gazelle\User($DB, $Cache, $UserID);
$User->forceCacheFlush($UserID == $LoggedUser['ID']);

?>
<div class="thin">
    <div class="header">
        <h2><?=Users::format_username($UserID, true, true, true, false, true)?></h2>
    </div>
    <div class="linkbox">
<?php
if (!$OwnProfile) {
?>
        <a href="inbox.php?action=compose&amp;to=<?=$UserID?>" class="brackets">Send message</a>
<?php if (!$User->isFriend($LoggedUser['ID'])) { ?>
        <a href="friends.php?action=add&amp;friendid=<?=$UserID?>&amp;auth=<?=$LoggedUser['AuthKey']?>" class="brackets">Add to friends</a>
<?php } ?>
        <a href="reports.php?action=report&amp;type=user&amp;id=<?=$UserID?>" class="brackets">Report user</a>
<?php
}

if (check_perms('users_edit_profiles', $Class) || $LoggedUser['ID'] == $UserID) {
?>
        <a href="user.php?action=edit&amp;userid=<?=$UserID?>" class="brackets">Settings</a>
<?php
}
if (check_perms('users_view_invites', $Class)) {
?>
        <a href="user.php?action=invite&amp;userid=<?=$UserID?>" class="brackets">Invites</a>
<?php
}
if (check_perms('admin_manage_permissions', $Class)) {
?>
        <a href="user.php?action=permissions&amp;userid=<?=$UserID?>" class="brackets">Permissions</a>
<?php
}
if (check_perms('users_view_ips', $Class)) {
?>
        <a href="user.php?action=sessions&amp;userid=<?=$UserID?>" class="brackets">Sessions</a>
        <a href="userhistory.php?action=copypaste&amp;userid=<?=$UserID?>" class="brackets">Generate Copy-Paste</a>
<?php
}
if (check_perms('admin_reports')) {
?>
        <a href="reportsv2.php?view=reporter&amp;id=<?=$UserID?>" class="brackets">Reports</a>
<?php
}
if (check_perms('users_mod')) {
?>
        <a href="userhistory.php?action=token_history&amp;userid=<?=$UserID?>" class="brackets">FL tokens</a>
<?php
}
if (check_perms('users_mod') || ($LoggedUser['ID'] == $UserID && check_perms('site_user_stats'))) {
?>
        <a href="user.php?action=stats&amp;userid=<?=$UserID?>" class="brackets">Stats</a>
<?php
}
if (check_perms('admin_clear_cache') && check_perms('users_override_paranoia')) {
?>
        <a href="user.php?action=clearcache&amp;id=<?=$UserID?>" class="brackets">Clear cache</a>
<?php
}
if (check_perms('users_mod')) {
?>
        <a href="#staff_tools" class="brackets">Jump to staff tools</a>
<?php
}
?>
    </div>

    <div class="sidebar">
<?php
if ($Avatar && Users::has_avatars_enabled()) {
?>
        <div class="box box_image box_image_avatar">
            <div class="head colhead_dark">Avatar</div>
            <div align="center">
<?=                Users::show_avatar($Avatar, $UserID, $Username, $HeavyInfo['DisableAvatars'])?>
            </div>
        </div>
<?php
}
if ($Enabled == 1 && $AcceptFL && (count($FL_Items) || isset($FL_OTHER_tokens))) {
?>
        <div class="box box_info box_userinfo_give_FL">
<?php
    if (isset($FL_OTHER_tokens)) {
?>
            <div class="head colhead_dark">Freeleech Tokens Given</div>
            <ul class="stats nobullet">
<?php
        if ($FL_OTHER_tokens > 0) {
            $s = $FL_OTHER_tokens > 1 ? 's' : '';
?>
            <li>You gave <?= $FL_OTHER_tokens ?> token<?= $s ?> to <?= $Username ?>. Your generosity is most appreciated!</li>
<?php
        } else {
?>
            <li>You attempted to give some tokens to <?= $Username ?> but something didn't work out.
            No points were spent.</li>
<?php
        }
?>
            </ul>
<?php
    }
    else {
?>
            <div class="head colhead_dark">Give Freeleech Tokens</div>
            <form class="fl_form" name="user" id="fl_form" action="user.php?id=<?= $UserID ?>" method="post">
                <ul class="stats nobullet">
<?php
        foreach ($FL_Items as $data) {
            $label_title = sprintf("This costs %d BP, which will leave you %d afterwards", $data['Price'], $data['After']);
?>
                    <li><input type="radio" name="fltype" id="fl-<?= $data['Label'] ?>" value="fl-<?= $data['Label'] ?>" />
                    <label title="<?= $label_title ?>" for="fl-<?= $data['Label'] ?>"> <?= $data['Name'] ?></label></li>
<?php
        }
?>
            <li><input type="submit" name="flsubmit" value="Send" /></li>
                </ul>
                <input type="hidden" name="action" value="fltoken" />
                <input type="hidden" name="auth" value="<?= $LoggedUser['AuthKey'] ?>" />
            </form>
<?php
    }
?>
        </div>
<?php
}
?>
        <div class="box box_info box_userinfo_stats">
            <div class="head colhead_dark">Statistics</div>
            <ul class="stats nobullet">
                <li>Joined: <?=$JoinedDate?></li>
<?php    if (($Override = check_paranoia_here('lastseen'))) { ?>
                <li <?=($Override === 2 ? 'class="paranoia_override"' : '')?>>Last seen: <?= time_diff($LastAccess) ?></li>
<?php
    }
    if (($Override = check_paranoia_here('uploaded'))) {
?>
                <li class="tooltip<?=($Override === 2 ? ' paranoia_override' : '')?>" title="<?=Format::get_size($Uploaded, 5)?>">Uploaded: <?=Format::get_size($Uploaded)?></li>
<?php
    }
    if (($Override = check_paranoia_here('downloaded'))) {
?>
                <li class="tooltip<?=($Override === 2 ? ' paranoia_override' : '')?>" title="<?=Format::get_size($Downloaded, 5)?>">Downloaded: <?=Format::get_size($Downloaded)?></li>
<?php
    }
    if (($Override = (check_paranoia_here('uploaded') && check_paranoia_here('downloaded')))) {
?>
                <li class="tooltip<?=($Override === 2 ? ' paranoia_override' : '')?>" title="<?=Format::get_size($Uploaded - $Downloaded, 5)?>">Buffer: <?=Format::get_size($Uploaded - $Downloaded)?></li>
<?php
    }
    if (($Override = check_paranoia_here('ratio'))) {
?>
                <li <?=($Override === 2 ? 'class="paranoia_override"' : '')?>>Ratio: <?=Format::get_ratio_html($Uploaded, $Downloaded)?></li>
<?php
    }
    if (($Override = check_paranoia_here('requiredratio')) && isset($RequiredRatio)) {
?>
                <li <?=($Override === 2 ? 'class="paranoia_override"' : '')?>>Required Ratio: <span class="tooltip" title="<?=number_format((double)$RequiredRatio, 5)?>"><?=number_format((double)$RequiredRatio, 2)?></span></li>
<?php
    }
    if (($Override = check_paranoia_here('bonuspoints')) && isset($BonusPoints)) {
?>
                <li <?=($Override === 2 ? 'class="paranoia_override"' : '')?>>Bonus Points: <?=number_format($BonusPoints)?><?php
        if (check_perms('admin_bp_history')) {
             printf('&nbsp;<a href="bonus.php?action=history&amp;userid=%d" class="brackets">History</a>', $UserID);
             $text = '<a href="bonus.php?action=bprates&userid=' . $UserID . '">Points Per Hour</a>';
        } else if ($OwnProfile) {
             printf('&nbsp;<a href="bonus.php?action=history" class="brackets">History</a>', $UserID);
             $text = '<a href="bonus.php?action=bprates">Points Per Hour</a>';
        } else {
            $text = 'Points Per Hour';
        }
                ?></li>
                <li <?=($Override === 2 ? 'class="paranoia_override"' : '')?>><?= $text ?>: <?=number_format($BonusPointsPerHour)?></li>
<?php
    }
    if ($OwnProfile || ($Override = check_paranoia_here(false)) || check_perms('users_mod')) {
?>
                <li <?=($Override === 2 ? 'class="paranoia_override"' : '')?>><a href="userhistory.php?action=token_history&amp;userid=<?=$UserID?>">Tokens</a>: <?=number_format($FLTokens)?></li>
<?php
    }
    if (($OwnProfile || check_perms('users_mod')) && $Warned != '0000-00-00 00:00:00') {
?>
                <li <?=($Override === 2 ? 'class="paranoia_override"' : '')?>>Warning expires in: <?=time_diff((date('Y-m-d H:i', strtotime($Warned))))?></li>
<?php    } ?>
            </ul>
        </div>
<?php
// Last.fm statistics and comparability
$LastFMUsername = LastFM::get_lastfm_username($UserID);
if ($LastFMUsername)  {
    LastFMView::render_sidebar($LastFMUsername, $UserID, $OwnProfile);
}

if (check_paranoia_here('requestsfilled_count') || check_paranoia_here('requestsfilled_bounty')) {
    list($RequestsFilled, $TotalBounty) = $User->requestsBounty();
} else {
    $RequestsFilled = $TotalBounty = 0;
}
if (check_paranoia_here('requestsvoted_count') || check_paranoia_here('requestsvoted_bounty')) {
    list($RequestsVoted, $TotalSpent) = $User->requestsVotes();
    list($RequestsCreated, $RequestsCreatedSpent) = $User->requestsCreated();
} else {
    $RequestsVoted = $TotalSpent = $RequestsCreated = $RequestsCreatedSpent = 0;
}

if (check_paranoia_here('uploads+')) {
    $Uploads = $User->uploadCount();
} else {
    $Uploads = 0;
}

if (check_paranoia_here('artistsadded')) {
    $ArtistsAdded = $User->artistsAdded();
} else {
    $ArtistsAdded = 0;
}

//Do the ranks
$UploadedRank = UserRank::get_rank('uploaded', $Uploaded);
$DownloadedRank = UserRank::get_rank('downloaded', $Downloaded);
$UploadsRank = UserRank::get_rank('uploads', $Uploads);
$RequestRank = UserRank::get_rank('requests', $RequestsFilled);
$PostRank = UserRank::get_rank('posts', $ForumPosts);
$BountyRank = UserRank::get_rank('bounty', $TotalSpent);
$ArtistsRank = UserRank::get_rank('artists', $ArtistsAdded);

if ($Downloaded == 0) {
    $Ratio = 1;
} elseif ($Uploaded == 0) {
    $Ratio = 0.5;
} else {
    $Ratio = round($Uploaded / $Downloaded, 2);
}
$OverallRank = UserRank::overall_score($UploadedRank, $DownloadedRank, $UploadsRank, $RequestRank, $PostRank, $BountyRank, $ArtistsRank, $Ratio);

?>
        <div class="box box_info box_userinfo_percentile">
            <div class="head colhead_dark">Percentile Rankings (hover for values)</div>
            <ul class="stats nobullet">
<?php    if (($Override = check_paranoia_here('uploaded'))) { ?>
                <li class="tooltip<?=($Override === 2 ? ' paranoia_override' : '')?>" title="<?=Format::get_size($Uploaded)?>">Data uploaded: <?=$UploadedRank === false ? 'Server busy' : number_format($UploadedRank)?></li>
<?php
    }
    if (($Override = check_paranoia_here('downloaded'))) { ?>
                <li class="tooltip<?=($Override === 2 ? ' paranoia_override' : '')?>" title="<?=Format::get_size($Downloaded)?>">Data downloaded: <?=$DownloadedRank === false ? 'Server busy' : number_format($DownloadedRank)?></li>
<?php
    }
    if (($Override = check_paranoia_here('uploads+'))) { ?>
                <li class="tooltip<?=($Override === 2 ? ' paranoia_override' : '')?>" title="<?=number_format($Uploads)?>">Torrents uploaded: <?=$UploadsRank === false ? 'Server busy' : number_format($UploadsRank)?></li>
<?php
    }
    if (($Override = check_paranoia_here('requestsfilled_count'))) { ?>
                <li class="tooltip<?=($Override === 2 ? ' paranoia_override' : '')?>" title="<?=number_format($RequestsFilled)?>">Requests filled: <?=$RequestRank === false ? 'Server busy' : number_format($RequestRank)?></li>
<?php
    }
    if (($Override = check_paranoia_here('requestsvoted_bounty'))) { ?>
                <li class="tooltip<?=($Override === 2 ? ' paranoia_override' : '')?>" title="<?=Format::get_size($TotalSpent)?>">Bounty spent: <?=$BountyRank === false ? 'Server busy' : number_format($BountyRank)?></li>
<?php    } ?>
                <li class="tooltip" title="<?=number_format($ForumPosts)?>">Posts made: <?=$PostRank === false ? 'Server busy' : number_format($PostRank)?></li>
<?php    if (($Override = check_paranoia_here('artistsadded'))) { ?>
                <li class="tooltip<?=($Override === 2 ? ' paranoia_override' : '')?>" title="<?=number_format($ArtistsAdded)?>">Artists added: <?=$ArtistsRank === false ? 'Server busy' : number_format($ArtistsRank)?></li>
<?php
    }
    if (check_paranoia_here(['uploaded', 'downloaded', 'uploads+', 'requestsfilled_count', 'requestsvoted_bounty', 'artistsadded'])) { ?>
                <li><strong>Overall rank: <?=$OverallRank === false ? 'Server busy' : number_format($OverallRank)?></strong></li>
<?php    } ?>
            </ul>
        </div>
<?php
    if (check_perms('users_mod', $Class) || check_perms('users_view_ips', $Class) || check_perms('users_view_keys', $Class)) {
?>
        <div class="box box_info box_userinfo_history">
            <div class="head colhead_dark">History</div>
            <ul class="stats nobullet">
<?php        if (check_perms('users_view_email', $Class)) { ?>
                <li>Emails: <?=number_format($User->emailCount())?> <a href="userhistory.php?action=email2&amp;userid=<?=$UserID?>" class="brackets">View</a>&nbsp;<a href="userhistory.php?action=email&amp;userid=<?=$UserID?>" class="brackets">Legacy view</a></li>
<?php
        }
        if (check_perms('users_view_ips', $Class)) {
?>
                <li>IPs: <?=number_format($User->siteIPCount())?> <a href="userhistory.php?action=ips&amp;userid=<?=$UserID?>" class="brackets">View</a>&nbsp;<a href="userhistory.php?action=ips&amp;userid=<?=$UserID?>&amp;usersonly=1" class="brackets">View users</a></li>
<?php            if (check_perms('users_view_ips', $Class) && check_perms('users_mod', $Class)) { ?>
                <li>Tracker IPs: <?=number_format($User->trackerIPCount())?> <a href="userhistory.php?action=tracker_ips&amp;userid=<?=$UserID?>" class="brackets">View</a></li>
<?php
            }
        }
        if (check_perms('users_view_keys', $Class)) {
?>
                <li>Passkeys: <?=number_format($User->passkeyCount())?> <a href="userhistory.php?action=passkeys&amp;userid=<?=$UserID?>" class="brackets">View</a></li>
<?php
        }
        if (check_perms('users_mod', $Class)) {
?>
                <li>Passwords: <?=number_format($User->passwordCount())?> <a href="userhistory.php?action=passwords&amp;userid=<?=$UserID?>" class="brackets">View</a></li>
                <li>Stats: N/A <a href="userhistory.php?action=stats&amp;userid=<?=$UserID?>" class="brackets">View</a></li>
<?php        } ?>
            </ul>
        </div>
<?php    } ?>
        <div class="box box_info box_userinfo_personal">
            <div class="head colhead_dark">Personal</div>
            <ul class="stats nobullet">
                <li>Class: <?=$ClassLevels[$Class]['Name']?></li>
<?php
$UserInfo = Users::user_info($UserID);
if (!empty($UserInfo['ExtraClasses'])) {
?>
                <li>
                    <ul class="stats">
<?php
    foreach ($UserInfo['ExtraClasses'] as $PermID => $Val) {
        ?>
                        <li><?=$Classes[$PermID]['Name']?></li>
<?php    } ?>
                    </ul>
                </li>
<?php
}
// An easy way for people to measure the paranoia of a user, for e.g. contest eligibility
if ($ParanoiaLevel == 0) {
    $ParanoiaLevelText = 'Off';
} elseif ($ParanoiaLevel == 1) {
    $ParanoiaLevelText = 'Very Low';
} elseif ($ParanoiaLevel <= 5) {
    $ParanoiaLevelText = 'Low';
} elseif ($ParanoiaLevel <= 20) {
    $ParanoiaLevelText = 'High';
} else {
    $ParanoiaLevelText = 'Very high';
}
?>
                <li>Paranoia level: <span class="tooltip" title="<?=$ParanoiaLevel?>"><?=$ParanoiaLevelText?></span></li>
<?php    if (check_perms('users_view_email', $Class) || $OwnProfile) { ?>
                <li>Email: <a href="mailto:<?=display_str($Email)?>"><?=display_str($Email)?></a>
<?php        if (check_perms('users_view_email', $Class)) { ?>
                    <a href="user.php?action=search&amp;email_history=on&amp;email=<?=display_str($Email)?>" title="Search" class="brackets tooltip">S</a>
<?php        } ?>
                </li>
<?php    }

if (check_perms('users_view_ips', $Class)) { ?>
                <li>IP: <?=Tools::display_ip($IP)?></li>
                <li>Host: <?=Tools::get_host_by_ajax($IP)?></li>
<?php
}

if (check_perms('users_view_keys', $Class) || $OwnProfile) {
?>
                <li>Passkey: <a href="#" id="passkey" onclick="togglePassKey('<?=display_str($torrent_pass)?>'); return false;" class="brackets">View</a></li>
<?php
}
if (check_perms('users_view_invites')) {
    if (!$InviterID) {
        $Invited = '<span style="font-style: italic;">Nobody</span>';
    } else {
        $Invited = "<a href=\"user.php?id=$InviterID\">$InviterName</a>";
    }
?>
                <li>Invited by: <?=$Invited?></li>
                <li>Invites: <?= ($DisableInvites ? 'X' : number_format($Invites)) ?>
                    <?= '(' . $User->pendingInviteCount() . ' in use)' ?></li>
<?php
}
if (Applicant::user_is_applicant($UserID) && (check_perms('admin_manage_applicants') || $OwnProfile)) {
?>
                <li>Roles applied for: <a href="/apply.php?action=view" class="brackets">View</a></li>
<?php
}

if (!isset($SupportFor)) {
    $SupportFor = $User->supportFor();
}
if ($Override = check_perms('users_mod') || $OwnProfile || !empty($SupportFor)) {
?>
                <li<?=(($Override === 2 || $SupportFor) ? ' class="paranoia_override"' : '')?>>Clients: <?=
                    implode('; ', $User->clients()) ?></li>
<?php
}

if ($OwnProfile || check_perms('users_mod')) {
?>
    <li>Password age: <?= $User->passwordAge() ?></li>
<?php }
if ($OwnProfile || check_perms('users_override_paranoia', $Class)) { ?>
    <li>IRC Key: <?=strlen($User->IRCKey()) ? 'Yes' : 'No' ?></li>
<?php } ?>
            </ul>
        </div>
<?php
include(__DIR__.'/community_stats.php');
DonationsView::render_donor_stats($UserID);
?>
    </div>
    <div class="main_column">
<?php
if (isset($RatioWatchEnds)
    && $RatioWatchEnds != '0000-00-00 00:00:00'
    && (time() < strtotime($RatioWatchEnds))
    && ($Downloaded * $RequiredRatio) > $Uploaded
) {
?>
        <div class="box">
            <div class="head">Ratio watch</div>
            <div class="pad">This user is currently on ratio watch and must upload <?=Format::get_size(($Downloaded * $RequiredRatio) - $Uploaded)?> in the next <?=time_diff($RatioWatchEnds)?>, or their leeching privileges will be revoked. Amount downloaded while on ratio watch: <?=Format::get_size($Downloaded - $RatioWatchDownload)?></div>
        </div>
<?php } ?>
        <div class="box">
            <div class="head">
                <?=!empty($InfoTitle) ? $InfoTitle : 'Profile';?>
                <span style="float: right;"><a href="#" onclick="$('#profilediv').gtoggle(); this.innerHTML = (this.innerHTML == 'Hide' ? 'Show' : 'Hide'); return false;" class="brackets">Hide</a></span>&nbsp;
            </div>
            <div class="pad profileinfo" id="profilediv">
<?php
if (!$Info) {
?>
                This profile is currently empty.
<?php
} else {
    echo Text::full_format($Info);
}
?>
            </div>
        </div>
<?php
DonationsView::render_profile_rewards($EnabledRewards, $ProfileRewards);

if (check_paranoia_here('snatched')) {
    $RecentSnatches = $User->recentSnatches();
    if (count($RecentSnatches)) {
?>
    <table class="layout recent" id="recent_snatches" cellpadding="0" cellspacing="0" border="0">
        <tr class="colhead">
            <td colspan="5">Recent Snatches</td>
        </tr>
        <tr>
<?php        foreach ($RecentSnatches as $recent) { ?>
            <td>
                <a href="torrents.php?id=<?= $recent['ID'] ?>">
                    <img class="tooltip" title="<?= $recent['Name'] ?>" alt="<?= $recent['Name'] ?>" src="<?= ImageTools::process($recent['WikiImage'], true) ?>" width="107" />
                </a>
            </td>
<?php        } ?>
        </tr>
    </table>
<?php
    }
}

if (check_paranoia_here('uploads')) {
    $RecentUploads = $User->recentUploads();
    if (count($RecentUploads)) {
?>
    <table class="layout recent" id="recent_uploads" cellpadding="0" cellspacing="0" border="0">
        <tr class="colhead">
            <td colspan="5">Recent Uploads</td>
        </tr>
        <tr>
<?php        foreach ($RecentUploads as $recent) { ?>
            <td>
                <a href="torrents.php?id=<?= $recent['ID'] ?>">
                    <img class="tooltip" title="<?= $recent['Name'] ?>" alt="<?= $recent['Name'] ?>" src="<?= ImageTools::process($recent['WikiImage'], true) ?>" width="107" />
                </a>
            </td>
<?php        } ?>
        </tr>
    </table>
<?php
    }
}

$Collages = $User->personalCollages();
$FirstCol = true;
foreach ($Collages as $CollageInfo) {
    list($CollageID, $CName) = $CollageInfo;
    $DB->prepared_query('
        SELECT ct.GroupID,
            tg.WikiImage,
            tg.CategoryID
        FROM collages_torrents AS ct
        INNER JOIN torrents_group AS tg ON (tg.ID = ct.GroupID)
        WHERE ct.CollageID = ?
        ORDER BY ct.Sort
        LIMIT 5
        ', $CollageID
    );
    $Collage = $DB->to_array(false, MYSQLI_ASSOC, false);
?>
    <table class="layout recent" id="collage<?=$CollageID?>_box" cellpadding="0" cellspacing="0" border="0">
        <tr class="colhead">
            <td colspan="5">
                <span style="float: left;">
                    <?=display_str($CName)?> - <a href="collages.php?id=<?=$CollageID?>" class="brackets">See full</a>
                </span>
                <span style="float: right;">
                    <a href="#" onclick="$('#collage<?=$CollageID?>_box .images').gtoggle(); this.innerHTML = (this.innerHTML == 'Hide' ? 'Show' : 'Hide'); return false;" class="brackets"><?=$FirstCol ? 'Hide' : 'Show' ?></a>
                </span>
            </td>
        </tr>
        <tr class="images<?=$FirstCol ? '' : ' hidden'?>">
<?php   foreach ($Collage as $C) {
            $Group = Torrents::get_groups([$C['GroupID']], true, true, false);
            $Name = Artists::display_artists(['1' => $Group['Artists']], false, true) . $Group['Name'];
?>
            <td>
                <a href="torrents.php?id=<?= $C['GroupID'] ?>">
                    <img class="tooltip" title="<?= $Name ?>" src="<?=ImageTools::process($C['WikiImage'], true)?>" alt="<?= $Name ?>" width="107" />
                </a>
            </td>
<?php    } ?>
        </tr>
    </table>
<?php
    $FirstCol = false;
}
?>
    <!-- for the "jump to staff tools" button -->
    <a id="staff_tools"></a>
<?php

// Linked accounts
if (check_perms('users_mod')) {
    include(__DIR__ . '/linkedfunctions.php');
    user_dupes_table($UserID);
}

if ((check_perms('users_view_invites')) && $Invited > 0) {
    include(__DIR__  . '/../../classes/invite_tree.class.php');
    $Tree = new INVITE_TREE($UserID, ['visible' => false]);
?>
        <div class="box" id="invitetree_box">
            <div class="head">
                Invite Tree <a href="#" onclick="$('#invitetree').gtoggle(); return false;" class="brackets">View</a>
            </div>
            <div id="invitetree" class="hidden">
<?php                $Tree->make_tree(); ?>
            </div>
        </div>
<?php
}

if (check_perms('users_mod')) {
    DonationsView::render_donation_history(Donations::get_donation_history($UserID));
}

// Requests
if (empty($LoggedUser['DisableRequests']) && check_paranoia_here('requestsvoted_list')) {
    $SphQL = new SphinxqlQuery();
    $SphQLResult = $SphQL->select('id, votes, bounty')
        ->from('requests, requests_delta')
        ->where('userid', $UserID)
        ->where('torrentid', 0)
        ->order_by('votes', 'desc')
        ->order_by('bounty', 'desc')
        ->limit(0, 100, 100) // Limit to 100 requests
        ->query();
    if ($SphQLResult->has_results()) {
        $SphRequests = $SphQLResult->to_array('id', MYSQLI_ASSOC);
?>
        <div class="box" id="requests_box">
            <div class="head">
                Requests <a href="#" onclick="$('#requests').gtoggle(); return false;" class="brackets">View</a>
            </div>
            <div id="requests" class="request_table hidden">
                <table cellpadding="6" cellspacing="1" border="0" class="border" width="100%">
                    <tr class="colhead_dark">
                        <td style="width: 48%;">
                            <strong>Request Name</strong>
                        </td>
                        <td>
                            <strong>Vote</strong>
                        </td>
                        <td>
                            <strong>Bounty</strong>
                        </td>
                        <td>
                            <strong>Added</strong>
                        </td>
                    </tr>
<?php
        $Row = 'a';
        $Requests = Requests::get_requests(array_keys($SphRequests));
        foreach ($SphRequests as $RequestID => $SphRequest) {
            $Request = $Requests[$RequestID];
            $VotesCount = $SphRequest['votes'];
            $Bounty = $SphRequest['bounty'] * 1024; // Sphinx stores bounty in kB
            $CategoryName = $Categories[$Request['CategoryID'] - 1];

            if ($CategoryName == 'Music') {
                $ArtistForm = Requests::get_artists($RequestID);
                $ArtistLink = Artists::display_artists($ArtistForm, true, true);
                $FullName = "$ArtistLink<a href=\"requests.php?action=view&amp;id=$RequestID\">$Request[Title] [$Request[Year]]</a>";
            } elseif ($CategoryName == 'Audiobooks' || $CategoryName == 'Comedy') {
                $FullName = "<a href=\"requests.php?action=view&amp;id=$RequestID\">$Request[Title] [$Request[Year]]</a>";
            } else {
                $FullName = "<a href=\"requests.php?action=view&amp;id=$RequestID\">$Request[Title]</a>";
            }
?>
                    <tr class="row<?=$Row === 'b' ? 'a' : 'b'?>">
                        <td>
                            <?=$FullName ?>
                            <div class="tags">
<?php
            $Tags = $Request['Tags'];
            $TagList = [];
            foreach ($Tags as $TagID => $TagName) {
                $TagList[] = "<a href=\"requests.php?tags=$TagName\">".display_str($TagName).'</a>';
            }
            $TagList = implode(', ', $TagList);
?>
                                <?=$TagList?>
                            </div>
                        </td>
                        <td>
                            <span id="vote_count_<?=$RequestID?>"><?=$VotesCount?></span>
<?php            if (check_perms('site_vote')) { ?>
                            &nbsp;&nbsp; <a href="javascript:Vote(0, <?=$RequestID?>)" class="brackets">+</a>
<?php            } ?>
                        </td>
                        <td>
                            <span id="bounty_<?=$RequestID?>"><?=Format::get_size($Bounty)?></span>
                        </td>
                        <td>
                            <?=time_diff($Request['TimeAdded']) ?>
                        </td>
                    </tr>
<?php        } ?>
                </table>
            </div>
        </div>
<?php
    }
}

$IsFLS = isset($LoggedUser['ExtraClasses'][FLS_TEAM]);
if (check_perms('users_mod', $Class) || $IsFLS) {
    $UserLevel = $LoggedUser['EffectiveClass'];
    $DB->prepared_query('
        SELECT
            SQL_CALC_FOUND_ROWS
            spc.ID,
            spc.Subject,
            spc.Status,
            spc.Level,
            spc.AssignedToUser,
            spc.Date,
            COUNT(spm.ID) AS Resplies,
            spc.ResolverID
        FROM staff_pm_conversations AS spc
        JOIN staff_pm_messages spm ON spm.ConvID = spc.ID
        WHERE spc.UserID = ?
            AND (spc.Level <= ? OR spc.AssignedToUser = ?)
        GROUP BY spc.ID
        ORDER BY spc.Date DESC
        ', $UserID, $UserLevel, $LoggedUser['ID']
    );
    if ($DB->has_results()) {
        $StaffPMs = $DB->to_array();
?>
        <div class="box" id="staffpms_box">
            <div class="head">
                Staff PMs <a href="#" onclick="$('#staffpms').gtoggle(); return false;" class="brackets">View</a>
            </div>
            <table width="100%" class="message_table hidden" id="staffpms">
                <tr class="colhead">
                    <td>Subject</td>
                    <td>Date</td>
                    <td>Assigned to</td>
                    <td>Replies</td>
                    <td>Resolved by</td>
                </tr>
<?php
        foreach ($StaffPMs as $StaffPM) {
            list($ID, $Subject, $Status, $Level, $AssignedToUser, $Date, $Replies, $ResolverID) = $StaffPM;
            // Get assigned
            if ($AssignedToUser == '') {
                // Assigned to class
                $Assigned = ($Level == 0) ? 'First Line Support' : $ClassLevels[$Level]['Name'];
                // No + on Sysops
                if ($Assigned != 'Sysop') {
                    $Assigned .= '+';
                }

            } else {
                // Assigned to user
                $Assigned = Users::format_username($UserID, true, true, true, true);
            }

            if ($ResolverID) {
                $Resolver = Users::format_username($ResolverID, true, true, true, true);
            } else {
                $Resolver = '(unresolved)';
            }

            ?>
                <tr>
                    <td><a href="staffpm.php?action=viewconv&amp;id=<?=$ID?>"><?=display_str($Subject)?></a></td>
                    <td><?=time_diff($Date, 2, true)?></td>
                    <td><?=$Assigned?></td>
                    <td><?=$Replies - 1?></td>
                    <td><?=$Resolver?></td>
                </tr>
<?php        } ?>
            </table>
        </div>
<?php
    }
}

// Displays a table of forum warnings viewable only to Forum Moderators
if ($LoggedUser['Class'] == 650 && check_perms('users_warn', $Class)) {
    $ForumWarnings = $User->forumWarning();
    if ($ForumWarnings) {
?>
<div class="box">
    <div class="head">Forum warnings</div>
    <div class="pad">
        <div id="forumwarningslinks" class="AdminComment" style="width: 98%;"><?=Text::full_format($ForumWarnings)?></div>
    </div>
</div>
<?php
    }
}
if (check_perms('users_mod', $Class)) { ?>
        <form class="manage_form" name="user" id="form" action="user.php" method="post">
        <input type="hidden" name="action" value="moderate" />
        <input type="hidden" name="userid" value="<?=$UserID?>" />
        <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />

        <div class="box box2" id="staff_notes_box">
            <div class="head">
                Staff Notes
                <a href="#" name="admincommentbutton" onclick="ChangeTo('text'); return false;" class="brackets">Edit</a>
                <a href="#" onclick="$('#staffnotes').gtoggle(); return false;" class="brackets">Toggle</a>
            </div>
            <div id="staffnotes" class="pad">
                <input type="hidden" name="comment_hash" value="<?=$CommentHash?>" />
                <div id="admincommentlinks" class="AdminComment" style="width: 98%;"><?=Text::full_format($AdminComment)?></div>
                <textarea id="admincomment" onkeyup="resize('admincomment');" class="AdminComment hidden" name="AdminComment" cols="65" rows="26" style="width: 98%;"><?=display_str($AdminComment)?></textarea>
                <a href="#" name="admincommentbutton" onclick="ChangeTo('text'); return false;" class="brackets">Toggle edit</a>
                <script type="text/javascript">
                    resize('admincomment');
                </script>
            </div>
        </div>

        <table class="layout" id="user_info_box">
            <tr class="colhead">
                <td colspan="2">
                    User Information
                </td>
            </tr>
<?php    if (check_perms('users_edit_usernames', $Class)) { ?>
            <tr>
                <td class="label">Username:</td>
                <td><input type="text" size="20" name="Username" value="<?=display_str($Username)?>" /></td>
            </tr>
<?php
    }
    if (check_perms('users_edit_titles')) {
?>
            <tr>
                <td class="label">Custom title:</td>
                <td><input type="text" class="wide_input_text" name="Title" value="<?=display_str($CustomTitle)?>" /></td>
            </tr>
<?php
    }

    if (check_perms('users_promote_below', $Class) || check_perms('users_promote_to', $Class - 1)) {
?>
            <tr>
                <td class="label">Primary class:</td>
                <td>
                    <select name="Class">
<?php
        foreach ($ClassLevels as $CurClass) {
            if ($CurClass['Secondary']) {
                continue;
            }
            elseif ($LoggedUser['ID'] != $UserID && !check_perms('users_promote_to', $Class-1) && $CurClass['Level'] == $LoggedUser['EffectiveClass']) {
                break;
            }
            elseif ($CurClass['Level'] > $LoggedUser['EffectiveClass']) {
                break;
            }
            if ($Class == $CurClass['Level']) {
                $Selected = ' selected="selected"';
            } else {
                $Selected = '';
            }
?>
                        <option value="<?=$CurClass['ID']?>"<?=$Selected?>><?=$CurClass['Name'].' ('.$CurClass['Level'].')'?></option>
<?php        } ?>
                    </select>
                </td>
            </tr>
<?php
    }

    if (check_perms('users_give_donor')) {
?>
            <tr>
                <td class="label">Donor:</td>
                <td><input type="checkbox" name="Donor"<?php if ($Donor == 1) { ?> checked="checked"<?php } ?> /></td>
            </tr>
<?php
    }
    if (check_perms('users_promote_below') || check_perms('users_promote_to')) { ?>
        <tr>
            <td class="label">Secondary classes:</td>
            <td>
<?php
        $DB->prepared_query('
            SELECT p.ID, p.Name, l.UserID
            FROM permissions AS p
            LEFT JOIN users_levels AS l ON (l.PermissionID = p.ID AND l.UserID = ?)
            WHERE p.Secondary = 1
            ORDER BY p.Name
            ', $UserID
        );
        $i = 0;
        while (list($PermID, $PermName, $IsSet) = $DB->next_record()) {
            $i++;
?>
                <input type="checkbox" id="perm_<?=$PermID?>" name="secondary_classes[]" value="<?=$PermID?>"<?php if ($IsSet) { ?> checked="checked"<?php } ?> />&nbsp;<label for="perm_<?=$PermID?>" style="margin-right: 10px;"><?=$PermName?></label>
<?php            if ($i % 3 == 0) {
                echo "\t\t\t\t<br />\n";
            }
        } ?>
            </td>
        </tr>
<?php }

    if (check_perms('users_make_invisible')) { ?>
            <tr>
                <td class="label">Visible in peer lists:</td>
                <td><input type="checkbox" name="Visible"<?php if ($Visible == 1) { ?> checked="checked"<?php } ?> /></td>
            </tr>
<?php }

    if (check_perms('admin_rate_limit_manage')) { ?>
            <tr id="comm_unlimited_download">
                <td class="label tooltip" title="If checked, user is allowed to download unlimited torrent files.">Unlimited Torrent Downloads</td>
                <td><input type="checkbox" name="unlimitedDownload" id="unlimitedDownload"<?= $UnlimitedDownload ? ' checked="checked"' : ''?> /></td>
            </tr>
<?php }

    if (check_perms('users_edit_ratio', $Class) || (check_perms('users_edit_own_ratio') && $UserID == $LoggedUser['ID'])) {
?>
            <tr>
                <td class="label tooltip" title="Upload amount in bytes. Also accepts e.g. +20GB or -35.6364MB on the end.">Uploaded:</td>
                <td>
                    <input type="hidden" name="OldUploaded" value="<?=$Uploaded?>" />
                    <input type="text" size="20" name="Uploaded" value="<?=$Uploaded?>" />
                </td>
            </tr>
            <tr>
                <td class="label tooltip" title="Download amount in bytes. Also accepts e.g. +20GB or -35.6364MB on the end.">Downloaded:</td>
                <td>
                    <input type="hidden" name="OldDownloaded" value="<?=$Downloaded?>" />
                    <input type="text" size="20" name="Downloaded" value="<?=$Downloaded?>" />
                </td>
            </tr>
            <tr>
                <td class="label tooltip" title="Bonus Points.">Bonus Points:</td>
                <td>
                    <input type="hidden" name="OldBonusPoints" value="<?=$BonusPoints?>" />
                    <input type="text" size="20" name="BonusPoints" value="<?=$BonusPoints?>" />
                </td>
            </tr>
            <tr>
                <td class="label tooltip" title="Enter a username.">Merge stats <strong>from:</strong></td>
                <td>
                    <input type="text" size="40" name="MergeStatsFrom" />
                </td>
            </tr>
<?php
    }

    if (check_perms('users_edit_invites')) {
?>
            <tr>
                <td class="label tooltip" title="Number of invites">Invites:</td>
                <td><input type="text" size="5" name="Invites" value="<?=$Invites?>" /></td>
            </tr>
<?php
    }

    if (check_perms('admin_manage_user_fls')) {
?>
            <tr>
                <td class="label tooltip" title="Number of FL tokens">FL Tokens:</td>
                <td><input type="text" size="5" name="FLTokens" value="<?=$FLTokens?>" /></td>
            </tr>
<?php
    }

    if (check_perms('admin_manage_fls') || (check_perms('users_mod') && $OwnProfile)) {
?>
            <tr>
                <td class="label tooltip" title="This is the message shown in the right-hand column on /staff.php">FLS/Staff remark:</td>
                <td><input type="text" class="wide_input_text" name="SupportFor" value="<?=display_str($SupportFor)?>" /></td>
            </tr>
<?php
    }

    if (check_perms('users_edit_reset_keys')) {
?>
            <tr>
                <td class="label">Reset:</td>
                <td>
                    <input type="checkbox" name="ResetRatioWatch" id="ResetRatioWatch" /> <label for="ResetRatioWatch">Ratio watch</label> |
                    <input type="checkbox" name="ResetPasskey" id="ResetPasskey" /> <label for="ResetPasskey">Passkey</label> |
                    <input type="checkbox" name="ResetAuthkey" id="ResetAuthkey" /> <label for="ResetAuthkey">Authkey</label> |
                    <input type="checkbox" name="ResetIPHistory" id="ResetIPHistory" /> <label for="ResetIPHistory">IP history</label> |
                    <input type="checkbox" name="ResetEmailHistory" id="ResetEmailHistory" /> <label for="ResetEmailHistory">Email history</label>
                    <br />
                    <input type="checkbox" name="ResetSnatchList" id="ResetSnatchList" /> <label for="ResetSnatchList">Snatch list</label> |
                    <input type="checkbox" name="ResetDownloadList" id="ResetDownloadList" /> <label for="ResetDownloadList">Download list</label>
                </td>
            </tr>
<?php
    }

    if (check_perms('users_edit_password')) {
?>
            <tr>
                <td class="label">New password:</td>
                <td>
                    <input type="text" size="30" id="change_password" name="ChangePassword" />
                    <button type="button" id="random_password">Generate</button>
                </td>
            </tr>

            <tr>
                <td class="label">Two-factor Authentication:</td>
                <td>
<?php      if ($FA_Key) { ?>
                    <a href="user.php?action=2fa&page=user&do=disable&userid=<?= $UserID ?>">Click here to disable</a>
<?php      } else { ?>
                    Currently Disabled
<?php      } ?>
                </td>
            </tr>

<?php    } ?>
        </table>

<?php    if (check_perms('users_warn')) { ?>
        <table class="layout" id="warn_user_box">
            <tr class="colhead">
                <td colspan="2">
                    Warnings
                </td>
            </tr>
            <tr>
                <td class="label">Warned:</td>
                <td>
                    <input type="checkbox" name="Warned"<?php if ($Warned != '0000-00-00 00:00:00') { ?> checked="checked"<?php } ?> />
                </td>
            </tr>
<?php        if ($Warned == '0000-00-00 00:00:00') { /* user is not warned */ ?>
            <tr>
                <td class="label">Expiration:</td>
                <td>
                    <select name="WarnLength">
                        <option value="">---</option>
                        <option value="1">1 week</option>
                        <option value="2">2 weeks</option>
                        <option value="4">4 weeks</option>
                        <option value="8">8 weeks</option>
                    </select>
                </td>
            </tr>
<?php        } else { /* user is warned */ ?>
            <tr>
                <td class="label">Extension:</td>
                <td>
                    <select name="ExtendWarning" onchange="ToggleWarningAdjust(this);">
                        <option>---</option>
                        <option value="1">1 week</option>
                        <option value="2">2 weeks</option>
                        <option value="4">4 weeks</option>
                        <option value="8">8 weeks</option>
                    </select>
                </td>
            </tr>
            <tr id="ReduceWarningTR">
                <td class="label">Reduction:</td>
                <td>
                    <select name="ReduceWarning">
                        <option>---</option>
                        <option value="1">1 week</option>
                        <option value="2">2 weeks</option>
                        <option value="4">4 weeks</option>
                        <option value="8">8 weeks</option>
                    </select>
                </td>
            </tr>
<?php        } ?>
            <tr>
                <td class="label tooltip" title="This message *will* be sent to the user in the warning PM!">Warning reason:</td>
                <td>
                    <input type="text" class="wide_input_text" name="WarnReason" />
                </td>
            </tr>
<?php    } ?>
        </table>
<?php  if (check_perms('users_disable_any')) { ?>
        <table class="layout">
            <tr class="colhead">
                <td colspan="2">
                    Lock Account
                </td>
            </tr>
            <tr>
                <td class="label">Lock Account:</td>
                <td>
                    <input type="checkbox" name="LockAccount" id="LockAccount" <?php if($LockedAccount) { ?> checked="checked" <?php } ?>/>
                </td>
            </tr>
            <tr>
                <td class="label">Reason:</td>
                <td>
                    <select name="LockReason">
                        <option value="---">---</option>
                        <option value="<?=STAFF_LOCKED?>" <?php if ($LockedAccount == STAFF_LOCKED) { ?> selected <?php } ?>>Staff Lock</option>
                    </select>
                </td>
            </tr>
        </table>
<?php  }  ?>
        <table class="layout" id="user_privs_box">
            <tr class="colhead">
                <td colspan="2">
                    User Privileges
                </td>
            </tr>
<?php    if (check_perms('users_disable_posts') || check_perms('users_disable_any')) {
        $Emails = $User->emailHistory();
?>
            <tr>
                <td class="label">Disable:</td>
                <td>
                    <input type="checkbox" name="DisablePosting" id="DisablePosting"<?php if ($DisablePosting == 1) { ?> checked="checked"<?php } ?> /> <label for="DisablePosting">Posting</label>
<?php        if (check_perms('users_disable_any')) { ?> |
                    <input type="checkbox" name="DisableAvatar" id="DisableAvatar"<?php if ($DisableAvatar == 1) { ?> checked="checked"<?php } ?> /> <label for="DisableAvatar">Avatar</label> |
                    <input type="checkbox" name="DisableForums" id="DisableForums"<?php if ($DisableForums == 1) { ?> checked="checked"<?php } ?> /> <label for="DisableForums">Forums</label> |
                    <input type="checkbox" name="DisableIRC" id="DisableIRC"<?php if ($DisableIRC == 1) { ?> checked="checked"<?php } ?> /> <label for="DisableIRC">IRC</label> |
                    <input type="checkbox" name="DisablePM" id="DisablePM"<?php if ($DisablePM == 1) { ?> checked="checked"<?php } ?> /> <label for="DisablePM">PM</label> |
                    <br /><br />

                    <input type="checkbox" name="DisableLeech" id="DisableLeech"<?php if ($DisableLeech == 0) { ?> checked="checked"<?php } ?> /> <label for="DisableLeech">Leech</label> |
                    <input type="checkbox" name="DisableRequests" id="DisableRequests"<?php if ($DisableRequests == 1) { ?> checked="checked"<?php } ?> /> <label for="DisableRequests">Requests</label> |
                    <input type="checkbox" name="DisableUpload" id="DisableUpload"<?php if ($DisableUpload == 1) { ?> checked="checked"<?php } ?> /> <label for="DisableUpload">Torrent upload</label> |
                    <input type="checkbox" name="DisablePoints" id="DisablePoints"<?php if ($DisablePoints == 1) { ?> checked="checked"<?php } ?> /> <label for="DisablePoints">Bonus Points</label>
                    <br /><br />

                    <input type="checkbox" name="DisableTagging" id="DisableTagging"<?php if ($DisableTagging == 1) { ?> checked="checked"<?php } ?> /> <label for="DisableTagging" class="tooltip" title="This only disables a user's ability to delete tags.">Tagging</label> |
                    <input type="checkbox" name="DisableWiki" id="DisableWiki"<?php if ($DisableWiki == 1) { ?> checked="checked"<?php } ?> /> <label for="DisableWiki">Wiki</label>
                    <br /><br />

                    <input type="checkbox" name="DisableInvites" id="DisableInvites"<?php if ($DisableInvites == 1) { ?> checked="checked"<?php } ?> /> <label for="DisableInvites">Invites</label>
                </td>
            </tr>
            <tr>
                <td class="label">Hacked:</td>
                <td>
                    <input type="checkbox" name="SendHackedMail" id="SendHackedMail" /> <label for="SendHackedMail">Send hacked account email</label> to
                    <select name="HackedEmail">
<?php
            foreach ($Emails as $Email) {
                list($Address, $IP) = $Email;
?>
                        <option value="<?=display_str($Address)?>"><?=display_str($Address)?> - <?=display_str($IP)?></option>
<?php            } ?>
                    </select>
                </td>
            </tr>

<?php
        }
    }

    if (check_perms('users_disable_any')) {
?>
            <tr>
                <td class="label">Account:</td>
                <td>
                    <select name="UserStatus">
                        <option value="0"<?php if ($Enabled == '0') { ?> selected="selected"<?php } ?>>Unconfirmed</option>
                        <option value="1"<?php if ($Enabled == '1') { ?> selected="selected"<?php } ?>>Enabled</option>
                        <option value="2"<?php if ($Enabled == '2') { ?> selected="selected"<?php } ?>>Disabled</option>
<?php        if (check_perms('users_delete_users')) { ?>
                        <optgroup label="-- WARNING --">
                            <option value="delete">Delete account</option>
                        </optgroup>
<?php        } ?>
                    </select>
                </td>
            </tr>
            <tr>
                <td class="label">User reason:</td>
                <td>
                    <input type="text" class="wide_input_text" name="UserReason" />
                </td>
            </tr>
            <tr>
                <td class="label tooltip" title="Enter a comma-delimited list of forum IDs.">Restricted forums:</td>
                <td>
                    <input type="text" class="wide_input_text" name="RestrictedForums" value="<?=display_str($RestrictedForums)?>" />
                </td>
            </tr>
            <tr>
                <td class="label tooltip" title="Enter a comma-delimited list of forum IDs.">Extra forums:</td>
                <td>
                    <input type="text" class="wide_input_text" name="PermittedForums" value="<?=display_str($PermittedForums)?>" />
                </td>
            </tr>

<?php    } ?>
        </table>
<?php    if (check_perms('users_logout')) { ?>
        <table class="layout" id="session_box">
            <tr class="colhead">
                <td colspan="2">
                    Session
                </td>
            </tr>
            <tr>
                <td class="label">Reset session:</td>
                <td><input type="checkbox" name="ResetSession" id="ResetSession" /></td>
            </tr>
            <tr>
                <td class="label">Log out:</td>
                <td><input type="checkbox" name="LogOut" id="LogOut" /></td>
            </tr>
        </table>
<?php
    }
    if (check_perms('users_mod')) {
        DonationsView::render_mod_donations($UserID);
    }
?>
        <table class="layout" id="submit_box">
            <tr class="colhead">
                <td colspan="2">
                    Submit
                </td>
            </tr>
            <tr>
                <td class="label tooltip" title="This message will be entered into staff notes only.">Reason:</td>
                <td>
                    <textarea rows="1" cols="35" class="wide_input_text" name="Reason" id="Reason" onkeyup="resize('Reason');"></textarea>
                </td>
            </tr>
            <tr>
                <td class="label">Paste user stats:</td>
                <td>
                    <button type="button" id="paster">Paste</button>
                </td>
            </tr>

            <tr>
                <td align="right" colspan="2">
                    <input type="submit" value="Save changes" />
                </td>
            </tr>
        </table>
        </form>
<?php
}
?>
    </div>
</div>
<?php
View::show_footer();
