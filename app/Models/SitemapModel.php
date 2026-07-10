<?php namespace App\Models;

use CodeIgniter\Model;
use Config\Globals;

class SitemapModel extends BaseModel
{
    public function __construct()
    {
        parent::__construct();
        $this->urls = array();
    }

    //get pages
    public function getPages()
    {
        return $this->db->table('pages')->join('languages', 'languages.id = pages.lang_id')->select('pages.*, languages.short_form AS lang_short_form')
            ->where('languages.status', 1)->where('pages.visibility', 1)->orderBy('languages.id')->get()->getResult();
    }

    //get categories
    public function getCategories()
    {
        return $this->db->table('categories')->join('languages', 'languages.id = categories.lang_id')
            ->select('categories.*, languages.short_form AS lang_short_form, (SELECT name_slug FROM categories AS tbl_categories WHERE tbl_categories.id = categories.parent_id) as parent_slug')
            ->where('languages.status', 1)->orderBy('languages.id, categories.id')->get()->getResult();
    }

    //get posts (full - only for backward compatibility; avoid using when many posts)
    public function getPosts()
    {
        return $this->db->table('posts')->join('languages', 'languages.id = posts.lang_id')->select('posts.*, languages.short_form AS lang_short_form')
            ->where('posts.visibility', 1)->where('posts.status', 1)->where('posts.is_scheduled', 0)->orderBy('languages.id, posts.id')->get()->getResult();
    }

    //get posts count for sitemap (low memory)
    public function getPostsCountForSitemap()
    {
        return $this->db->table('posts')
            ->where('visibility', 1)->where('status', 1)->where('is_scheduled', 0)->countAllResults();
    }

    //get posts chunk for sitemap - only minimal columns to save memory
    public function getPostsChunkForSitemap($limit, $offset)
    {
        return $this->db->table('posts')
            ->join('languages', 'languages.id = posts.lang_id')
            ->select('posts.id, posts.lang_id, posts.title_slug, posts.created_at, posts.updated_at, languages.short_form AS lang_short_form')
            ->where('posts.visibility', 1)->where('posts.status', 1)->where('posts.is_scheduled', 0)
            ->orderBy('posts.lang_id', 'ASC')->orderBy('posts.id', 'ASC')
            ->limit((int)$limit, (int)$offset)
            ->get()->getResult();
    }

    //get tags chunk - minimal columns (tag_slug, lang_id only)
    public function getTagsChunkForSitemap($limit, $offset)
    {
        $sql = "SELECT tags.tag_slug, posts.lang_id FROM tags 
                INNER JOIN posts ON posts.id = tags.post_id 
                WHERE posts.status = 1 AND posts.visibility = 1 AND posts.is_scheduled = 0 
                GROUP BY tags.tag_slug, posts.lang_id 
                ORDER BY posts.lang_id, tags.tag_slug 
                LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
        return $this->db->query($sql)->getResult();
    }

    //get tags (full - only for backward compatibility)
    public function getTags()
    {
        return $this->db->table('tags')->join('posts', 'posts.id = tags.post_id')->join('users', 'posts.user_id = users.id')
            ->select('tags.tag_slug, tags.tag, posts.lang_id')->where('posts.status', 1)->where('posts.visibility', 1)
            ->where('posts.is_scheduled', 0)->get()->getResult();
    }

    //update sitemap settings
    public function updateSitemapSettings()
    {
        $data = [
            'sitemap_frequency' => inputPost('frequency'),
            'sitemap_last_modification' => inputPost('last_modification'),
            'sitemap_priority' => inputPost('priority')
        ];
        $this->db->table('general_settings')->where('id', 1)->update($data);
    }

    //add sitemap item
    public function add($loc, $changeFreq = NULL, $lastMod = NULL, $priority = NULL, $priorityValue = NULL, $lastModTime = NULL)
    {
        $item = new \stdClass();
        $item->loc = $loc;
        $item->lastMod = $lastMod;
        $item->lastModTime = $lastModTime;
        $item->changeFreq = $changeFreq;
        $item->priority = $priority;
        $item->priorityValue = $priorityValue;
        $this->urls[] = $item;
        return true;
    }

    //add static page urls
    public function addStaticURLs($frequency, $lastModification, $priority, $lastModTime)
    {
        $this->add(base_url(), $frequency, $lastModification, 1, 1, $lastModTime);
    }

    //add page urls
    public function addPageURLs($frequency, $lastModification, $priority, $lastModTime)
    {
        $pages = $this->getPages();
        if (!empty($pages)) {
            foreach ($pages as $page) {
                $baseURL = $this->generateBaseURLByLang($page->lang_id, $page->lang_short_form);
                if (!empty($baseURL)) {
                    $this->add($baseURL . $page->slug, $frequency, $lastModification, $priority, 0.8, $lastModTime);
                }
            }
        }
    }

    //add category urls
    public function addCategoryURLs($frequency, $lastModification, $priority, $lastModTime)
    {
        $categories = $this->getCategories();
        if (!empty($categories)) {
            foreach ($categories as $category) {
                $baseURL = $this->generateBaseURLByLang($category->lang_id, $category->lang_short_form);
                if (!empty($baseURL)) {
                    $url = "";
                    if (!empty($category->parent_slug)) {
                        $url = $baseURL . $category->parent_slug . '/' . $category->name_slug;
                    } else {
                        $url = $baseURL . $category->name_slug;
                    }
                    $this->add($url, $frequency, $lastModification, $priority, 0.8, $lastModTime);
                }
            }
        }
    }

    //add post urls (chunked to avoid memory exhaustion)
    public function addPostUrls($frequency, $lastModification, $priority, $lastModTime)
    {
        $chunkSize = 2000;
        $offset = 0;
        while (true) {
            $posts = $this->getPostsChunkForSitemap($chunkSize, $offset);
            if (empty($posts)) {
                break;
            }
            foreach ($posts as $post) {
                $baseURL = $this->generateBaseURLByLang($post->lang_id, $post->lang_short_form);
                if (!empty($baseURL)) {
                    $this->add($baseURL . $post->title_slug, $frequency, $lastModification, $priority, 0.8, isset($post->updated_at) && $post->updated_at ? $post->updated_at : $post->created_at);
                }
            }
            unset($posts);
            $offset += $chunkSize;
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        }
    }

    //add tag urls (chunked to avoid memory exhaustion)
    public function addTagUrls($frequency, $lastModification, $priority, $lastModTime)
    {
        $chunkSize = 2000;
        $offset = 0;
        while (true) {
            $tags = $this->getTagsChunkForSitemap($chunkSize, $offset);
            if (empty($tags)) {
                break;
            }
            foreach ($tags as $tag) {
                if (!empty($tag->tag_slug)) {
                    $baseURL = generateBaseURLByLangId($tag->lang_id);
                    if (!empty($baseURL)) {
                        $url = $baseURL . getRoute('tag', true) . $tag->tag_slug;
                        $this->add($url, $frequency, $lastModification, $priority, 0.8, $lastModTime);
                    }
                }
            }
            unset($tags);
            $offset += $chunkSize;
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        }
    }

    //generate sitemap (memory-optimized: chunked post/tag loading + optional memory limit)
    public function generateSitemap()
    {
        @ini_set('memory_limit', '768M');
        // Remove old sitemap files so no stale sitemap-2.xml etc. remain when URL count changes
        $oldFiles = glob(FCPATH . 'sitemap*.xml');
        if (!empty($oldFiles)) {
            foreach ($oldFiles as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        }
        $settingsModel = new SettingsModel();
        $generalSettings = $settingsModel->getGeneralSettings();
        if (empty($generalSettings)) {
            return false;
        }
        $frequency = $generalSettings->sitemap_frequency;
        $lastMod = $generalSettings->sitemap_last_modification;
        $priority = $generalSettings->sitemap_priority;
        $this->addStaticURLs($frequency, $lastMod, 1, NULL);
        $this->addPageURLs($frequency, $lastMod, $priority, NULL);
        $this->addCategoryURLs($frequency, $lastMod, $priority, NULL);
        $this->addPostUrls($frequency, $lastMod, $priority, NULL);
        $this->addTagUrls($frequency, $lastMod, $priority, NULL);
        $totalUrls = countItems($this->urls);
        if ($totalUrls > 49000) {
            $arrayURLs = array_chunk($this->urls, 49000);
            $this->urls = [];
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
            $i = 0;
            foreach ($arrayURLs as $arrayURL) {
                $fullPath = FCPATH . 'sitemap.xml';
                if ($i != 0) {
                    $fullPath = FCPATH . 'sitemap-' . $i . '.xml';
                }
                $this->exportSitemap($fullPath, $arrayURL);
                unset($arrayURL);
                $i++;
            }
        } else {
            $fullPath = FCPATH . 'sitemap.xml';
            $this->exportSitemap($fullPath, $this->urls);
        }
    }

    //export sitemap
    public function exportSitemap($fullPath, $array)
    {
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8" ?><urlset/>');
        $xml->addAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        foreach ($array as $url) {
            $child = $xml->addChild('url');
            $urlLoc = '';
            if (!empty($url->loc)) {
                $urlLoc = htmlspecialchars(strtolower($url->loc));
            }
            $child->addChild('loc', $urlLoc);
            if (isset($url->lastMod) && $url->lastMod != 'none') {
                if ($url->lastMod == 'server_response') {
                    $child->addChild('lastmod', date('Y-m-d'));
                } else {
                    $child->addChild('lastmod', $url->lastModTime);
                }
            }
            if (isset($url->changeFreq) && $url->changeFreq != 'none') {
                $child->addChild('changefreq', $url->changeFreq);
            }
            if (isset($url->priority) && $url->priority != 'none') {
                $child->addChild('priority', $url->priorityValue);
            }
        }
        $xml->saveXML($fullPath);
    }

    //generate base URL by language
    public function generateBaseURLByLang($langId, $shortForm)
    {
        if ($langId == $this->generalSettings->site_lang) {
            return base_url() . '/';
        } else {
            return base_url($shortForm) . '/';
        }
    }

}
