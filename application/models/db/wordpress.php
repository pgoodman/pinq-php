<?php

!defined('DIR_APPLICATION') && exit();

$model->store('categories', struct('wp_categories')->

    cat_ID              ->int(10)->primary_key()->auto_increment(1)
                        ->mapTo('categories', 'category_parent')->
    cat_name            ->string(55)->
    category_nicename   ->string(200)->
    category_description->string()->
    category_parent     ->int(4)->mapTo('categories', 'cat_ID')->
    category_count      ->int(5)->
    link_count          ->int(10)->
    posts_private       ->int(1)->
    links_private       ->int(1)->
    
    relatesTo('categories')
);

$model->store('category_links', struct('wp_link2cat')->

    rel_id              ->int(10)->primary_key()->auto_increment(1)->
    link_id             ->int(10)->mapTo('links', 'link_id')->
    category_id         ->int(10)->mapTp('categories', 'cat_ID')->
    
    relatesTo('categories')->
    relatesTo('links')
);

$model->store('category_posts', struct('wp_post2cat')->

    rel_id              ->int(10)->primary_key()->auto_increment(1)->
    post_id             ->int(10)->mapTo('posts', 'ID')->
    category_id         ->int(10)->mapTo('categories', 'cat_ID')->
    
    relateTo('posts')->
    relateTo('categories')
);
