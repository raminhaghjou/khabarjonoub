<?php
/**
 * Schema.org NewsArticle / Article JSON-LD for LLM and search engine discovery.
 * Used on single post/page for ChatGPT, Perplexity, Claude, etc.
 */
if (empty($post)) {
    return;
}
$articleUrl = currentFullURL();
$imgUrl = isset($ogImage) ? $ogImage : getPostImage($post, 'big');
$authorName = isset($post->author_username) ? $post->author_username : (isset($postUser->username) ? $postUser->username : (isset($baseSettings->application_name) ? $baseSettings->application_name : ''));
$datePublished = !empty($post->created_at) ? date('c', strtotime($post->created_at)) : '';
$dateModified = !empty($post->updated_at) ? date('c', strtotime($post->updated_at)) : $datePublished;
$description = !empty($post->summary) ? $post->summary : (isset($description) ? $description : '');
$headline = $post->title;
if (!empty($post->pre_title)) {
    $headline = $post->pre_title . ' - ' . $post->title;
}
$jsonLd = [
    '@context' => 'https://schema.org',
    '@type' => 'NewsArticle',
    'headline' => $headline,
    'description' => $description,
    'image' => $imgUrl,
    'datePublished' => $datePublished,
    'dateModified' => $dateModified,
    'author' => [
        '@type' => 'Person',
        'name' => $authorName,
    ],
    'publisher' => [
        '@type' => 'Organization',
        'name' => (isset($baseSettings->application_name) ? $baseSettings->application_name : (isset($baseSettings->site_title) ? $baseSettings->site_title : '')),
        'logo' => [
            '@type' => 'ImageObject',
            'url' => getLogo(),
        ],
    ],
    'mainEntityOfPage' => [
        '@type' => 'WebPage',
        '@id' => $articleUrl,
    ],
    'url' => $articleUrl,
];
if (!empty($post->keywords)) {
    $jsonLd['keywords'] = $post->keywords;
}
?>
<script type="application/ld+json"><?= json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?></script>
