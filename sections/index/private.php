<?php
Text::$TOC = true;

$NewsCount = 5;
if (!$News = $Cache->get_value('news')) {
    $DB->prepared_query('
        SELECT
            ID,
            Title,
            Body,
            Time
        FROM news
        ORDER BY Time DESC
        LIMIT ?
        ', $NewsCount
    );
    $News = $DB->to_array(false, MYSQLI_NUM, false);
    $Cache->cache_value('news', $News, 3600);
    if (count($News) > 0) {
        $Cache->cache_value('news_latest_id', $News[0][0], 0);
        $Cache->cache_value('news_latest_title', $News[0][1], 0);
    }
}

if (count($News) > 0 && $LoggedUser['LastReadNews'] != $News[0][0]) {
    $Cache->begin_transaction('user_info_heavy_'.$LoggedUser['ID']);
    $Cache->update_row(false, ['LastReadNews' => $News[0][0]]);
    $Cache->commit_transaction(0);
    $DB->prepared_query('
        UPDATE users_info
        SET LastReadNews = ?
        WHERE UserID = ?
        ', $News[0][0], $LoggedUser['ID']
    );
    $LoggedUser['LastReadNews'] = $News[0][0];
}

View::show_header('News', 'bbcode,news_ajax');
?>
<div class="thin">
    <div class="sidebar">
<?php
include('month_album.php');
include('vanity_album.php');

if (check_perms('users_mod')) {
?>
        <div class="box">
            <div class="head colhead_dark">
                <strong><a href="staffblog.php">Latest staff blog posts</a></strong>
            </div>
<?php
if (($Blog = $Cache->get_value('staff_blog')) === false) {
    $DB->prepared_query('
        SELECT
            b.ID,
            um.Username,
            b.Title,
            b.Body,
            b.Time
        FROM staff_blog AS b
        LEFT JOIN users_main AS um ON (b.UserID = um.ID)
        ORDER BY Time DESC
    ');
    $Blog = $DB->to_array(false, MYSQLI_NUM);
    $Cache->cache_value('staff_blog', $Blog, 86400);
}
if (($SBlogReadTime = $Cache->get_value('staff_blog_read_'.$LoggedUser['ID'])) === false) {
    $DB->prepared_query('
        SELECT Time
        FROM staff_blog_visits
        WHERE UserID = ?
        ', $LoggedUser['ID']
    );
    if (list($SBlogReadTime) = $DB->next_record()) {
        $SBlogReadTime = strtotime($SBlogReadTime);
    } else {
        $SBlogReadTime = 0;
    }
    $Cache->cache_value('staff_blog_read_'.$LoggedUser['ID'], $SBlogReadTime, 86400);
}
?>
            <ul class="stats nobullet">
<?php
$End = min(count($Blog), 5);
for ($i = 0; $i < $End; $i++) {
    list($BlogID, $Author, $Title, $Body, $BlogTime) = $Blog[$i];
    $BlogTime = strtotime($BlogTime);
?>
                <li>
                    <?=$SBlogReadTime < $BlogTime ? '<strong>' : ''?><?=($i + 1)?>.
                    <a href="staffblog.php#blog<?=$BlogID?>"><?=$Title?></a>
                    <?=$SBlogReadTime < $BlogTime ? '</strong>' : ''?>
                </li>
<?php
}
?>
            </ul>
        </div>
<?php    } ?>
        <div class="box">
            <div class="head colhead_dark"><strong><a href="blog.php">Latest blog posts</a></strong></div>
<?php
if (($Blog = $Cache->get_value('blog')) === false) {
    $DB->prepared_query('
        SELECT
            b.ID,
            um.Username,
            b.UserID,
            b.Title,
            b.Body,
            b.Time,
            b.ThreadID
        FROM blog AS b
        LEFT JOIN users_main AS um ON (b.UserID = um.ID)
        ORDER BY Time DESC
        LIMIT 20
    ');
    $Blog = $DB->to_array();
    $Cache->cache_value('blog', $Blog, 1209600);
}
?>
            <ul class="stats nobullet">
<?php
if (count($Blog) < 5) {
    $Limit = count($Blog);
} else {
    $Limit = 5;
}
for ($i = 0; $i < $Limit; $i++) {
    list($BlogID, $Author, $AuthorID, $Title, $Body, $BlogTime, $ThreadID) = $Blog[$i];
?>
                <li>
                    <?=($i + 1)?>. <a href="blog.php#blog<?=$BlogID?>"><?=$Title?></a>
                </li>
<?php
}
?>
            </ul>
        </div>

<?php include('contest_leaderboard.php'); ?>

        <div class="box">
            <div class="head colhead_dark"><strong>Stats</strong></div>
            <ul class="stats nobullet">
<?php if (USER_LIMIT > 0) { ?>
                <li>Maximum users: <?=number_format(USER_LIMIT) ?></li>
<?php
}

$UserCount = Users::get_enabled_users_count();
?>
                <li>Enabled users: <?=number_format($UserCount)?> <a href="stats.php?action=users" class="brackets">Details</a></li>
<?php
$UserStats = \Gazelle\User::globalActivityStats($DB, $Cache);
?>
                <li>Users active today: <?=number_format($UserStats['Day'])?> (<?=number_format($UserStats['Day'] / $UserCount * 100, 2)?>%)</li>
                <li>Users active this week: <?=number_format($UserStats['Week'])?> (<?=number_format($UserStats['Week'] / $UserCount * 100, 2)?>%)</li>
                <li>Users active this month: <?=number_format($UserStats['Month'])?> (<?=number_format($UserStats['Month'] / $UserCount * 100, 2)?>%)</li>
<?php

if (($TorrentCount = $Cache->get_value('stats_torrent_count')) === false) {
    $DB->prepared_query('
        SELECT count(*)
        FROM torrents
    ');
    list($TorrentCount) = $DB->next_record();
    $Cache->cache_value('stats_torrent_count', $TorrentCount, 86400 + rand(0, 3600));
}

if (($AlbumCount = $Cache->get_value('stats_album_count')) === false) {
    $DB->prepared_query("
        SELECT count(*)
        FROM torrents_group
        WHERE CategoryID = '1'
    ");
    list($AlbumCount) = $DB->next_record();
    $Cache->cache_value('stats_album_count', $AlbumCount, 86400 + rand(0, 3600));
}

if (($ArtistCount = $Cache->get_value('stats_artist_count')) === false) {
    $DB->prepared_query('
        SELECT count(*)
        FROM artists_group
    ');
    list($ArtistCount) = $DB->next_record();
    $Cache->cache_value('stats_artist_count', $ArtistCount, 86400 + rand(0, 3600));
}

if (($PerfectCount = $Cache->get_value('stats_perfect_count')) === false) {
    $DB->prepared_query("
        SELECT count(*)
        FROM torrents
        WHERE Format = 'FLAC'
            AND (
                (Media = 'CD' AND LogScore = 100)
                OR
                (Media IN ('Vinyl', 'WEB', 'DVD', 'Soundboard', 'BD', 'SACD', 'BD'))
            )
    ");
    list($PerfectCount) = $DB->next_record();
    $Cache->cache_value('stats_perfect_count', $PerfectCount, 86400 + rand(0, 3600)); // half a day plus fuzz
}
?>
                <li>Torrents: <?=number_format($TorrentCount)?></li>
                <li>Releases: <?=number_format($AlbumCount)?></li>
                <li>Artists: <?=number_format($ArtistCount)?></li>
                <li>"Perfect" FLACs: <?=number_format($PerfectCount)?></li>
<?php
//End Torrent Stats

if (($CollageCount = $Cache->get_value('stats_collages')) === false) {
    $DB->prepared_query('
        SELECT count(*)
        FROM collages
    ');
    list($CollageCount) = $DB->next_record();
    $Cache->cache_value('stats_collages', $CollageCount, 86400 + rand(0, 3600));
}
?>
                <li>Collages: <?=number_format($CollageCount)?></li>
<?php

if (($RequestStats = $Cache->get_value('stats_requests')) === false) {
    $DB->prepared_query('
        SELECT count(*), sum(FillerID > 0)
        FROM requests
    ');
    list($RequestCount, $FilledCount) = $DB->next_record();
    $Cache->cache_value('stats_requests', [$RequestCount, $FilledCount], 3600 * 3 + rand(0, 1800)); // three hours plus fuzz
} else {
    list($RequestCount, $FilledCount) = $RequestStats;
}
$RequestPercentage = $RequestCount > 0 ? $FilledCount / $RequestCount * 100 : 0;
?>
                <li>Requests: <?=number_format($RequestCount)?> (<?=number_format($RequestPercentage, 2)?>% filled)</li>
<?php

if ($SnatchStats = $Cache->get_value('stats_snatches')) {
?>
                <li>Snatches: <?=number_format($SnatchStats)?></li>
<?php
}

if (($PeerStats = $Cache->get_value('stats_peers')) === false) {
    //Cache lock!
    $PeerStatsLocked = $Cache->get_value('stats_peers_lock');
    if (!$PeerStatsLocked) {
        $Cache->cache_value('stats_peers_lock', 1, 30);
        $DB->prepared_query("
            SELECT IF(remaining=0,'Seeding','Leeching') AS Type, COUNT(uid)
            FROM xbt_files_users
            WHERE active = 1
            GROUP BY Type
        ");
        $PeerCount = $DB->to_array(0, MYSQLI_NUM, false);
        $SeederCount = $PeerCount['Seeding'][1] ?: 0;
        $LeecherCount = $PeerCount['Leeching'][1] ?: 0;
        $Cache->cache_value('stats_peers', [$LeecherCount, $SeederCount], 1209600); // 2 week cache
        $Cache->delete_value('stats_peers_lock');
    }
} else {
    $PeerStatsLocked = false;
    list($LeecherCount, $SeederCount) = $PeerStats;
}

if (!$PeerStatsLocked) {
    $Ratio = Format::get_ratio_html($SeederCount, $LeecherCount);
    $PeerCount = number_format($SeederCount + $LeecherCount);
    $SeederCount = number_format($SeederCount);
    $LeecherCount = number_format($LeecherCount);
} else {
    $PeerCount = $SeederCount = $LeecherCount = $Ratio = 'Server busy';
}
?>
                <li>Peers: <?=$PeerCount?></li>
                <li>Seeders: <?=$SeederCount?></li>
                <li>Leechers: <?=$LeecherCount?></li>
                <li>Seeder/leecher ratio: <?=$Ratio?></li>
            </ul>
        </div>
<?php
if (($TopicID = $Cache->get_value('polls_featured')) === false) {
    $DB->prepared_query('
        SELECT TopicID
        FROM forums_polls
        ORDER BY Featured DESC
        LIMIT 1
    ');
    list($TopicID) = $DB->next_record();
    $Cache->cache_value('polls_featured', $TopicID, 3600 * 6);
}
if ($TopicID) {
    if (($Poll = $Cache->get_value("polls_$TopicID")) === false) {
        $DB->prepared_query('
            SELECT Question, Answers, Featured, Closed
            FROM forums_polls
            WHERE TopicID = ?
            ', $TopicID
        );
        list($Question, $Answers, $Featured, $Closed) = $DB->next_record(MYSQLI_NUM, [1]);
        $Answers = unserialize($Answers);
        $DB->prepared_query("
            SELECT Vote, count(*)
            FROM forums_polls_votes
            WHERE Vote != '0'
                AND TopicID = ?
            GROUP BY Vote
            ", $TopicID
        );
        $VoteArray = $DB->to_array(false, MYSQLI_NUM);

        $Votes = [];
        foreach ($VoteArray as $VoteSet) {
            list($Key,$Value) = $VoteSet;
            $Votes[$Key] = $Value;
        }

        for ($i = 1, $il = count($Answers); $i <= $il; ++$i) {
            if (!isset($Votes[$i])) {
                $Votes[$i] = 0;
            }
        }
        $Cache->cache_value("polls_$TopicID", [$Question, $Answers, $Votes, $Featured, $Closed], 86400 + rand(0, 3600));
    } else {
        list($Question, $Answers, $Votes, $Featured, $Closed) = $Poll;
    }

    if (!empty($Votes)) {
        $TotalVotes = array_sum($Votes);
        $MaxVotes = max($Votes);
    } else {
        $TotalVotes = 0;
        $MaxVotes = 0;
    }

    $DB->prepared_query('
        SELECT Vote
        FROM forums_polls_votes
        WHERE UserID = ?
            AND TopicID = ?
        ', $LoggedUser['ID'], $TopicID
    );
    list($UserResponse) = $DB->next_record();
    if (!empty($UserResponse) && $UserResponse != 0) {
        $Answers[$UserResponse] = '&raquo; '.$Answers[$UserResponse];
    }
?>
        <div class="box">
            <div class="head colhead_dark"><strong>Poll<?php if ($Closed) { echo ' [Closed]'; } ?></strong></div>
            <div class="pad">
                <p><strong><?=display_str($Question)?></strong></p>
<?php    if ($UserResponse !== null || $Closed) { ?>
                <ul class="poll nobullet">
<?php        foreach ($Answers as $i => $Answer) {
            if ($TotalVotes > 0) {
                $Ratio = $Votes[$i] / $MaxVotes;
                $Percent = $Votes[$i] / $TotalVotes;
            } else {
                $Ratio = 0;
                $Percent = 0;
            }
?>                    <li><?=display_str($Answers[$i])?> (<?=number_format($Percent * 100, 2)?>%)</li>
                    <li class="graph">
                        <span class="left_poll"></span>
                        <span class="center_poll" style="width: <?=number_format($Ratio * 100, 2)?>%;"></span>
                        <span class="right_poll"></span>
                        <br />
                    </li>
<?php        } ?>
                </ul>
                <strong>Votes:</strong> <?=number_format($TotalVotes)?><br />
<?php     } else { ?>
                <div id="poll_container">
                <form class="vote_form" name="poll" id="poll" action="">
                    <input type="hidden" name="action" value="poll" />
                    <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
                    <input type="hidden" name="topicid" value="<?=$TopicID?>" />
<?php         foreach ($Answers as $i => $Answer) { ?>
                    <input type="radio" name="vote" id="answer_<?=$i?>" value="<?=$i?>" />
                    <label for="answer_<?=$i?>"><?=display_str($Answers[$i])?></label><br />
<?php         } ?>
                    <br /><input type="radio" name="vote" id="answer_0" value="0" /> <label for="answer_0">Blank&#8202;&mdash;&#8202;Show the results!</label><br /><br />
                    <input type="button" onclick="ajax.post('index.php', 'poll', function(response) { $('#poll_container').raw().innerHTML = response } );" value="Vote" />
                </form>
                </div>
<?php     } ?>
                <br /><strong>Topic:</strong> <a href="forums.php?action=viewthread&amp;threadid=<?=$TopicID?>">Visit</a>
            </div>
        </div>
<?php
}
?>
    </div>
    <div class="main_column">
<?php

$Recommend = $Cache->get_value('recommend');
$Recommend_artists = $Cache->get_value('recommend_artists');

if (!is_array($Recommend) || !is_array($Recommend_artists)) {
    $DB->prepared_query('
        SELECT
            tr.GroupID,
            tr.UserID,
            u.Username,
            tg.Name,
            tg.TagList
        FROM torrents_recommended AS tr
        INNER JOIN torrents_group AS tg ON (tg.ID = tr.GroupID)
        LEFT JOIN users_main AS u ON (u.ID = tr.UserID)
        ORDER BY tr.Time DESC
        LIMIT 10
    ');
    $Recommend = $DB->to_array();
    $Cache->cache_value('recommend', $Recommend, 86400 + rand(0, 3600));

    $Recommend_artists = Artists::get_artists($DB->collect('GroupID'));
    $Cache->cache_value('recommend_artists', $Recommend_artists, 86400 + rand(0, 3600));
}

if (count($Recommend) >= 4) {
$Cache->increment('usage_index');
?>
    <div class="box" id="recommended">
        <div class="head colhead_dark">
            <strong>Latest Vanity House additions</strong>
            <a href="#" onclick="$('#vanityhouse').gtoggle(); this.innerHTML = (this.innerHTML == 'Hide' ? 'Show' : 'Hide'); return false;" class="brackets">Show</a>
        </div>

        <table class="torrent_table hidden" id="vanityhouse">
<?php
    foreach ($Recommend as $Recommendations) {
        list($GroupID, $UserID, $Username, $GroupName, $TagList) = $Recommendations;
        $TagsStr = '';
        if ($TagList) {
            // No vanity.house tag.
            $Tags = explode(' ', str_replace('_', '.', $TagList));
            $TagLinks = [];
            foreach ($Tags as $Tag) {
                if ($Tag == 'vanity.house') {
                    continue;
                }
                $TagLinks[] = "<a href=\"torrents.php?action=basic&amp;taglist=$Tag\">$Tag</a> ";
            }
            $TagStr = "<br />\n<div class=\"tags\">".implode(', ', $TagLinks).'</div>';
        }
?>
            <tr>
                <td>
                    <?=Artists::display_artists($Recommend_artists[$GroupID]) ?>
                    <a href="torrents.php?id=<?=$GroupID?>"><?=$GroupName?></a> (by <?=Users::format_username($UserID, false, false, false)?>)
                    <?=$TagStr?>
                </td>
            </tr>
<?php    } ?>
        </table>
    </div>
<!-- END recommendations section -->
<?php
}
$Count = 0;
foreach ($News as $NewsItem) {
    list($NewsID, $Title, $Body, $NewsTime) = $NewsItem;
    if (strtotime($NewsTime) > time()) {
        continue;
    }
?>
        <div id="news<?=$NewsID?>" class="box news_post">
            <div class="head">
                <strong><?=Text::full_format($Title)?></strong> <?=time_diff($NewsTime);?>
<?php    if (check_perms('admin_manage_news')) { ?>
                - <a href="tools.php?action=editnews&amp;id=<?=$NewsID?>" class="brackets">Edit</a>
<?php    } ?>
            <span style="float: right;"><a href="#" onclick="$('#newsbody<?=$NewsID?>').gtoggle(); this.innerHTML = (this.innerHTML == 'Hide' ? 'Show' : 'Hide'); return false;" class="brackets">Hide</a></span>
            </div>

            <div id="newsbody<?=$NewsID?>" class="pad"><?=Text::full_format($Body)?></div>
        </div>
<?php
    if (++$Count > ($NewsCount - 1)) {
        break;
    }
}
?>
        <div id="more_news" class="box">
            <div class="head">
                <em><span><a href="#" onclick="news_ajax(event, 3, <?=$NewsCount?>, <?=check_perms('admin_manage_news') ? 1 : 0; ?>, false); return false;">Click to load more news</a>.</span> To browse old news posts, <a href="forums.php?action=viewforum&amp;forumid=12">click here</a>.</em>
            </div>
        </div>
    </div>
</div>
<?php
View::show_footer(['disclaimer'=>true]);
