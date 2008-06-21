<?php

!defined('DIR_APPLICATION') && exit();

// job postings
$model->store('job_postings', struct('jobs_JobPostings')->
    Id                      ->int(11)->primary_key()->auto_increment(1)->
    ContentId               ->int(11)
                            ->mapTo('content', 'Id')->
    Instructions            ->string()->
    EmployerName            ->string(100)->
    ClickThroughUrl         ->string(150)->

    relatesTo('users', through('user_content_roles'))->
    relatesTo('tags', through('content'))
);

// cntent
$model->store('content', struct('www_Content')->
    Id                      ->int(11)->primary_key()->auto_increment(1)
                            ->mapTo('job_postings', 'ContentId')
                            ->mapTo('content_tags', 'ContentId')->
    Title                   ->string(75)->
    ContentHtml             ->string()->
    
    relatesTo('tags', through('content_tags'))->
    relatesTo('users', through('user_content_roles'))
);

// content tags, link content and tags tables together
$model->store('content_tags', struct('www_ContentTags')->
    Id                      ->int(11)->primary_key()->auto_increment(1)->
    ContentId               ->int(11)->mapTo('content', 'Id')->
    TagId                   ->int(11)->mapTo('tags', 'Id')
);

// tags
$model->store('tags', struct('www_Tags')->
    Id                      ->int(11)->primary_key()->auto_increment(1)
                            ->mapTo('content_tags', 'TagId')->
    Name                    ->string(35)->
    
    relatesTo('content', through('content_tags'))->
    relatesTo('job_postings', through('content'))
);

// users
$model->store('users', struct('auth_Users')->
    Id                      ->int(11)->primary_key()->auto_increment(1)
                            ->mapTo('user_content_roles', 'UserId')->
    Email                   ->string(150)->
    
    relatesTo('content', through('user_content_roles'))
);

// link content to users
$model->store('user_content_roles', struct('www_UserContentRoles')->
    Id                      ->int(11)->primary_key()->auto_increment(1)->
    UserId                  ->int(11)->mapTo('users', 'Id')->
    ContentId               ->int(11)->mapTo('content', 'Id')
);
