<?php

error_reporting(E_ALL | E_STRICT);

define('DIR_SYSTEM', dirname(__FILE__) .'/system/');

require_once DIR_SYSTEM .'/interfaces.php';
require_once DIR_SYSTEM .'/stack.php';
require_once DIR_SYSTEM .'/pql/__init__.php';

$query = where()->imm(1)->add->in->imm(2)->mul->imm(3)->add->imm(4)->out->sub->imm(5)->
         order()->left->ASC;

print_r($query->getPredicates());

exit;

/*
define('DIR_WORKING', dirname(__FILE__));

require_once DIR_WORKING .'/system/__init__.php';
require_once DIR_WORKING .'/system/abstract-query.php';
require_once DIR_WORKING .'/system/abstract-model.php';
require_once DIR_WORKING .'/system/model-dictionary.php';
require_once DIR_WORKING .'/system/concrete-query.php';
require_once DIR_WORKING .'/system/sql-query.php';


$model = new ModelDictionary();
*/
/**
 * Example with heirarchical models.
 */
/*
$model->create('xml', struct()->

    rss->model(struct()->

        channel->model(struct()->

            title      ->string()->
            description->string()->
            pubDate    ->string()->
            
            item->model(struct()->
                title      ->string()->
                description->string()->
                pubDate    ->string()
            )
        )
    )
);*/

/**
 * Example with highly normalized database tables.
 */
/*
// users table
$model->create('users', struct()->
    id      ->int()->primary_key()
            ->mapTo('posts', 'user_id')->
    name    ->string(20)->
    relatesTo('posts')->
    relatesTo('profiles')
);

// post table
$model->create('posts', struct()->
    id      ->int()->primary_key()->
    user_id ->int()
            ->mapTo('users', 'id')->
    title   ->string(255)->
    content_id->int()
            ->mapTo('content', 'id')->  
    relatesTo('profiles', through('users'))->
    relatesTo('content')
);

// table that holds user profile information
$model->create('profiles', struct()->
    user_id ->int()
            ->mapTo('users', 'id')->
    full_name->string(30)
);

// content table that would hold post and other info
$model->create('content', struct()->
    id      ->int()->primary_key()->
    body    ->string()
);

$query = from('posts', 'p')->select(ALL)->
         from('profiles')->count('user_id')->
         from('content')->select('body')->
         link('p', 'profiles')->
         link('p', 'content')->
         where->p('id')->eq(_)->
         and_->profiles('user_id')->gt_eq->p('id')->
         group->p('id')->order->p('id')->asc->limit(_, _);

// compile the SELECT query
echo SqlQuery::compileSelect($query, $model) ."\n";

// query to update a post and its content
$query = in('posts')->set(array('user_id' => _, 'title' => _))->
         in('content')->set('body', _)->
         link('posts', 'content')-> // a direct link is required for updates/inserts    
         where->posts('id')->eq(_);

// from the above query, compile the UPDATE, INSERT, and DELETE queries
echo SqlQuery::compileUpdate($query, $model) ."\n";
print_r(SqlQuery::compileInsert($query, $model));
echo SqlQuery::compileDelete($query, $model);
*/
/*

SELECT p.*, content.body AS body, COUNT(profiles.user_id) AS user_id 
FROM (posts p  INNER JOIN (users t1  INNER JOIN  profiles ON t1.id=profiles.user_id) ON p.user_id=t1.id INNER JOIN  content ON p.content_id=content.id) 
WHERE p.id=? 
AND profiles.user_id>= p.id 
GROUP BY p.id 
ORDER BY p.id ASC 
LIMIT ?, ?

UPDATE posts ,content
SET posts.user_id=?, posts.title=?, content.body=? 
WHERE ( posts.id=?) 
AND content.id= posts.content_id

// note: INSERT queries does not try to resolve any relations
Array
(
    [0] => INSERT INTO posts SET user_id=?, title=?
    [1] => INSERT INTO content SET body=?
)

DELETE FROM posts ,content 
WHERE ( posts.id=?) 
AND content.id= posts.content_id

*/


