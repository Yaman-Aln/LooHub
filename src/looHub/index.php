<?php
/*
 * @constants
 */
$search_limit = 10;

define('IN_PHPBB', true);
$phpbb_root_path = (defined('PHPBB_ROOT_PATH')) ? PHPBB_ROOT_PATH : './forum/';
$phpEx = substr(strrchr(__FILE__, '.'), 1);
include($phpbb_root_path . 'common.' . $phpEx);
include($phpbb_root_path . 'includes/bbcode.' . $phpEx);
include($phpbb_root_path . 'includes/functions_display.' . $phpEx);
//LooHub constants
if (file_exists('./includes/constants.' . $phpEx))
    include('./includes/constants.' . $phpEx);
else
    include('./includes/constants_ref.' . $phpEx);



// Start session management
$user->session_begin();
$auth->acl($user->data);
$user->setup('viewforum');

/* create_where_clauses( int[] gen_id, String type )
 * This function outputs an SQL WHERE statement for use when grabbing 
 * posts and topics 
 */
function create_where_clauses($gen_id, $type) {
    global $db, $auth;

    $size_gen_id = sizeof($gen_id);

    switch ($type) {
        case 'forum':
            $type = 'forum_id';
            break;
        case 'topic':
            $type = 'topic_id';
            break;
        default:
            trigger_error('No type defined');
    }

    // Set $out_where to nothing, this will be used of the gen_id
    // size is empty, in other words "grab from anywhere" with
    // no restrictions
    $out_where = '';

    if ($size_gen_id > 0) {
        // Get a list of all forums the user has permissions to read
        $auth_f_read = array_keys($auth->acl_getf('f_read', true));

        if ($type == 'topic_id') {
            $sql = 'SELECT topic_id FROM ' . TOPICS_TABLE . '
                            WHERE ' . $db->sql_in_set('topic_id', $gen_id) . '
                            AND ' . $db->sql_in_set('forum_id', $auth_f_read);

            $result = $db->sql_query($sql);

            while ($row = $db->sql_fetchrow($result)) {
                // Create an array with all acceptable topic ids
                $topic_id_list[] = $row['topic_id'];
            }

            unset($gen_id);

            $gen_id = $topic_id_list;
            $size_gen_id = sizeof($gen_id);
        }

        $j = 0;

        for ($i = 0; $i < $size_gen_id; $i++) {
            $id_check = (int) $gen_id[$i];

            // If the type is topic, all checks have been made and the query can start to be built
            if ($type == 'topic_id') {
                $out_where .= ($j == 0) ? 'WHERE ' . $type . ' = ' . $id_check . ' ' : 'OR ' . $type . ' = ' . $id_check . ' ';
            }

            // If the type is forum, do the check to make sure the user has read permissions
            else if ($type == 'forum_id' && $auth->acl_get('f_read', $id_check)) {
                $out_where .= ($j == 0) ? 'WHERE ' . $type . ' = ' . $id_check . ' ' : 'OR ' . $type . ' = ' . $id_check . ' ';
            }

            $j++;
        }
    }

    if ($out_where == '' && $size_gen_id > 0) {
        trigger_error('A list of topics/forums has not been created');
    }

    return $out_where;
}

/*
 * Populate recent topics given an accessible forum id
 */
function recent_topics($forum_ids) {
    global $db, $search_limit;
    $forum_id_where = create_where_clauses($forum_ids, 'forum');
    $recent_topics = 'SELECT * FROM ' . TOPICS_TABLE . '
                                ' . $forum_id_where . '
                                  AND topic_status <> ' . ITEM_MOVED . '
                                  AND topic_approved = 1
                                ORDER BY topic_last_post_time DESC';
    $recent_topics_result = $db->sql_query_limit($recent_topics, $search_limit);
    return $recent_topics_result;
}

function forum_url($forum_id) {
    global $phpbb_root_path, $phpEx;
    return append_sid($phpbb_root_path . 'viewforum.' . $phpEx . '?f=' . $forum_id);
}

/**
* Returns user avatar as an html image with set height and width 
* @param mediumint(8) unsigned $user_id Description
* @return varchar html image with max width and height of 100px
*/
function user_avatar($user_id){
   global $db;
   $img_height=70;
   $img_width=$img_height;
   $user_array = array(
           'SELECT' => 'u.*',
           'FROM' => array(USERS_TABLE => 'u'),
           'WHERE' => 'u.user_id=' . $user_id,
       );
   $user = $db->sql_build_query('SELECT', $user_array);
   $user_result = $db->sql_query($user);
   $u_row = $db->sql_fetchrow($user_result);
   return get_user_avatar($u_row['user_avatar'], $u_row['user_avatar_type'], $img_width, $img_height);
}

function post_message($post_id){
   global $db;
   $post_array = array(
           'SELECT' => 'p.post_text',
           'FROM' => array(POSTS_TABLE => 'p'),
           'WHERE' => 'p.post_id=' . $post_id,
       );
   $post = $db->sql_build_query('SELECT', $post_array);
   $post_result = $db->sql_query($post);
   $post_row = $db->sql_fetchrow($post_result);
   
   return nl2br($post_row['post_text']);
}


/*
 * Build popular topics lists
 */
$forum_id_where = create_where_clauses($LOO_HUB_ALL_FORUMS_IDS, 'forum');
$popular_topics = 'SELECT * FROM ' . TOPICS_TABLE . '
                                ' . $forum_id_where . '
                                  AND topic_status <> ' . ITEM_MOVED . '
                                  AND topic_approved = 1
                                ORDER BY topic_views DESC';
$popular_topics_result = $db->sql_query_limit($popular_topics, $search_limit);

/*
 * Build bullet news list
 */
$bullet_news_forum_id_where = create_where_clauses($BULLET_NEWS_FORUMS_IDS, 'forum');
$bullet_news_topics_arry = array(
                        'SELECT' => 't.*, f.*',
                        'FROM' => array(
                            TOPICS_TABLE => 't',
                        ),
                        'LEFT_JOIN' => array(
                            array(
                                'FROM' => array(FORUMS_TABLE => 'f'),
                                'ON' => 'f.forum_id = t.forum_id'
                            )
                        ),
                        'WHERE' => str_replace(array('WHERE ', 'forum_id'), array('', 't.forum_id'), $bullet_news_forum_id_where) . '
                                            AND t.topic_status <> ' . ITEM_MOVED . '
                                            AND t.topic_approved = 1',
                        'ORDER_BY' => 't.topic_id DESC',
                    );
$bullet_news_topics = $db->sql_build_query('SELECT', $bullet_news_topics_arry);
$bullet_news_topics_result = $db->sql_query_limit($bullet_news_topics, $search_limit);

//Build most recent topics in each category list
$recent_topics_result = recent_topics($LOO_HUB_ALL_FORUMS_IDS);
$calendar_topics_result = recent_topics($CALENDAR_FORUMS_IDS);
$looHub_topics_result = recent_topics($LOO_HUB_COMMUNITY_FORUMS_IDS);
$school_topics_result = recent_topics($SCHOOL_FORUMS_IDS);
$fanzone_topics_result = recent_topics($FAN_ZONE_FORUMS_IDS);
$lolz_topics_result = recent_topics($LOLZ_FORUMS_IDS);
?>


<!DOCTYPE HTML>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
        <title>LooHub Home</title>

        <link type="text/css" href="http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.21/themes/base/jquery-ui.css" rel="Stylesheet" />
        <link href="styles/stylesheet.css" rel="stylesheet" type="text/css">

        <script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js"></script>
        <script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.8.18/jquery-ui.min.js"></script>
        <!--[if lte IE 7]>
        <style>
        .content { margin-right: -1px; } /* this 1px negative margin can be placed on any of the columns in this layout with the same corrective effect. */
        ul.nav a { zoom: 1; }  /* the zoom property gives IE the hasLayout trigger it needs to correct extra whiltespace between the links */
        </style>
        <![endif]-->
    </head>

    <body>
        <div class="container">
            <header class="header">
                <a class="logo" href="/">
                    <img src="images/bannerlogo.png" alt="LooHub Logo" width="350" height="120" id="img-logo" />
                </a>
                <aside class="ads">
                    <div class="ads-content">
                        <img alt="Questions Ad" src="images/ads/QuestionsAdd.png" style="
                             min-height: 100%;
                             max-width: 100%"/>
                    </div>
                </aside>
                <ul class="menu">
                    <li><a class="menu-item-1 forums" href="<?php echo forum_url(LOO_HUB_FORUM_ID) ?>">Forums</a></li>
                    <li><a class="menu-item-2 reviews" href="<?php echo forum_url(REVIEWS_FORUM_ID) ?>">Reviews</a></li>
                    <li><a class="menu-item-3 advice" href="<?php echo forum_url(ADVICE_FORUM_ID) ?>">Advice</a></li>
                    <li><a class="menu-item-4 events" href="<?php echo forum_url(XCURRIC_FORUM_ID) ?>">Xcurric</a></li>
                    <li><a class="menu-item-5 lolz"  href="<?php echo forum_url(MARKET_FORUM_ID) ?>">Market</a></li>
                    <li><a class="menu-item-6 arcade" href="<?php echo forum_url(ARCADE_FORUM_ID) ?>">Arcade</a></li>
                </ul>
                <!-- end .header -->
            </header>
            <div class="sidebar1">
                <aside class="ads">
                    <div class="ads-content">
                        <img alt="Bird Ad" src="images/ads/BirdsAD.png" />
                    </div>
                </aside>
                <div class="bullet-news">
                    <a href="<?php echo forum_url(BULLET_NEWS_ID) ?>">
                        <h1 class="bn-heading">Bullet News</h1>
                    </a>
                    <ul class="bn-list">
                    <?php
                    function populate_bullet_news($bullet_news_result) {
                        global $db, $phpbb_root_path, $phpEx;
                        while ($bullet_news_row = $db->sql_fetchrow($bullet_news_result)) {
                            $topic_title = $bullet_news_row['topic_title'];
                            $topic_link = append_sid("{$phpbb_root_path}viewtopic.$phpEx", 
                                                                'f=' . $bullet_news_row['forum_id'] . '&amp;
                                                                 t=' . $bullet_news_row['topic_id']);
                            $forum_title = $bullet_news_row['forum_name'];
                            echo '<li class="bn-item">
                                    <h2 class="bn-item-heading">' . $forum_title . '</h2>
                                    <a href="' . $topic_link . '">
                                        <p class="bn-item-text">' . $topic_title . '</p>
                                    </a>
                                  </li>';
                        }
                    }

                    populate_bullet_news($bullet_news_topics_result);
                    ?>
                    </ul>
                </div><!-- Bullet news -->
                <!-- end .sidebar1 -->
            </div>
            <div class="content">
                <div class="hot-forums">
                    <h1>Hot Forums</h1>
                    <div id="tabs-left">
                        <ul>
                            <li><a href="#tabs-1">Recent</a></li>
                            <li><a href="#tabs-2">Popular</a></li>
                            <li><a href="#tabs-3">Calendar</a></li>
                            <li><a href="#tabs-4">looHUB</a></li>
                            <li><a href="#tabs-5">School</a></li>
                            <li><a href="#tabs-6">FANzone</a></li>
                            <li><a href="#tabs-7">LOLz</a></li>
                        </ul>
                        <div id="tabs-1">
                            <?php
                            function populate_hot_forums($topics_result) {
                                global $db, $user, $phpbb_root_path, $phpEx;
                                echo '<ul class="hf-topic-list">';
                                while ($topics_row = $db->sql_fetchrow($topics_result)) {
                                    $topic_title = $topics_row['topic_title'];
                                    $topic_date = $user->format_date($topics_row['topic_last_post_time']);
                                    $topic_last_author = get_username_string('username', $topics_row['topic_last_poster_id'], $topics_row['topic_last_poster_name'], $topics_row['topic_last_poster_colour']);
                                    $topic_last_post_id = $topics_row['topic_last_post_id'];
                                    $topic_link = append_sid("{$phpbb_root_path}viewtopic.$phpEx", 
                                                                    'f=' . $topics_row['forum_id'] . 
                                                                    '&amp;t=' . $topics_row['topic_id']) .  
                                                                    '&#35;p' . $topics_row['topic_last_post_id'];
                                    $user_img = user_avatar($topics_row['topic_last_poster_id']);
                                    echo  '<li class="hf-topic">
                                                <dl class="hf-topic-text">
                                                    ' . $user_img . '
                                                    <dt class="hf-topic-author">' . $topic_last_author . '</dt>
                                                    <dd class="hf-topic-date-time">' . $topic_date . '</dd>
                                                    <dd class="hf-topic-title">' . $topic_title . ': </dd>
                                                    <dd class="hf-topic-message">' . post_message($topic_last_post_id) . '</dd>
                                                    <dd class="hf-topic-link"><a href="' . $topic_link . '">Go To Topic</a></dd>
                                                </dl>
                                            </li>';
                                }
                                echo '</ul>';
                            }

                            populate_hot_forums($recent_topics_result);
                            ?>
                        </div>
                        <div id="tabs-2">
                            <?php
                            populate_hot_forums($popular_topics_result);
                            ?>
                        </div>
                        <div id="tabs-3">
                            <?php
                            populate_hot_forums($calendar_topics_result);
                            ?>
                        </div>
                        <div id="tabs-4">
                            <?php
                            populate_hot_forums($looHub_topics_result);
                            ?>
                        </div>
                        <div id="tabs-5">
                            <?php
                            populate_hot_forums($school_topics_result);
                            ?>
                        </div>
                        <div id="tabs-6">
                            <?php
                            populate_hot_forums($fanzone_topics_result);
                            ?>
                        </div>
                        <div id="tabs-7">
                            <?php
                            populate_hot_forums($lolz_topics_result);
                            ?>
                        </div>
                    </div>
                </div><!-- Hot Forums -->
                <!-- end .content -->
            </div>
            <footer class="footer">
<?php
echo '&#169; ' . date('Y') . ' uniHub';
?>
            </footer>
            <!-- end .container -->
        </div>
        <script>
            $(document).ready(function(){
                $( "#tabs-left" ).tabs();
            })
        </script>
    </body>
</html>
