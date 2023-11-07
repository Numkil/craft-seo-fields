<?php

namespace studioespresso\seofields\controllers;

use Craft;
use craft\helpers\DateTimeHelper;
use craft\helpers\Db;
use craft\helpers\UrlHelper;
use craft\i18n\Locale;
use craft\web\Controller;
use studioespresso\seofields\records\NotFoundRecord;
use studioespresso\seofields\records\RedirectRecord;

class CpApiController extends Controller
{
    public const NOT_FOUND_BASE = "seo-fields/cp-api/not-found";
    public const REDIRECT_BASE = "seo-fields/cp-api/redirect";

    /**
     * @param null $siteHandle
     * @return \yii\web\Response
     */
    public function actionNotFound()
    {
        $sort = $this->request->getQueryParam('sort');
        $search = $this->request->getQueryParam('search');
        if (!$sort) {
            $sort = "counter|desc";
        };

        $page = $this->request->getQueryParam('page', 1);
        list($key, $direction) = explode("|", $sort);

        $total = NotFoundRecord::find()->count();
        $limit = 20;

        $query = NotFoundRecord::find();
        $site = $this->request->getQueryParam('site');
        if ($site) {
            $site = Craft::$app->getSites()->getSiteByHandle($site);
            $query->orWhere(Db::parseParam('siteId', $site->id));
        }

        if ($search) {
            $query->andWhere([
                'or',
                "urlPath LIKE '%{$search}%'",
                "fullUrl LIKE '%{$search}%'",
            ]);
        }
        if ($total > $limit) {
            $query->offset($page * 10);
            $query->limit($limit);
        }
        $query->orderBy($key . " " . $direction);
        $rows = [];

        $allSites = Craft::$app->getSites()->getAllSites();

        $formatter = Craft::$app->getFormatter();
        foreach ($query->all() as $row) {
            $lastHit = DateTimeHelper::toDateTime($row->dateLastHit);
            $row = [
                'id' => $row->id,
                'title' => $row->urlPath,
                'hits' => $row->counter,
                'siteId' => $row->siteId,
                'lastHit' => $formatter->asDatetime($lastHit, Locale::LENGTH_SHORT),
                'site' => Craft::$app->getSites()->getSiteById($row->siteId)->name,
                'hasRedirect' => $row->handled ? $row->handled : $row,
            ];

            $rows[] = $row;
        }
        $nextPageUrl = self::NOT_FOUND_BASE;
        $prevPageUrl = self::NOT_FOUND_BASE;
        $lastPage = (int)ceil($total / $limit);
        $to = $page === $lastPage ? $total : ($total < $limit ? $total : ($page * $limit));

        return $this->asJson([
            'pagination' => [
                'total' => (int)$total,
                'per_page' => (int)$limit,
                'current_page' => (int)$page,
                'last_page' => (int)$lastPage,
                'next_page_url' => $nextPageUrl,
                'prev_page_url' => $prevPageUrl,
                'from' => (int)(($page * $limit) - $limit) + 1,
                'to' => (int)$to,
            ],
            'data' => $rows,
        ]);
    }

    public function actionRedirects()
    {
        $sort = $this->request->getQueryParam('sort');
        $search = $this->request->getQueryParam('search');
        if (!$sort) {
            $sort = "counter|desc";
        };

        $page = $this->request->getQueryParam('page', 1);
        list($key, $direction) = explode("|", $sort);

        $limit = 20;

        $query = RedirectRecord::find();

        $site = $this->request->getQueryParam('site');
        if ($site) {
            $site = Craft::$app->getSites()->getSiteByHandle($site);
            $query->orWhere(Db::parseParam('siteId', $site->id));
        }
        if ($search) {
            $query->andWhere([
                'or',
                "pattern LIKE '%{$search}%'",
                "redirect LIKE '%{$search}%'",
            ]);
        }
        $query->orderBy($key . " " . $direction);
        $rows = [];

        $allSites = Craft::$app->getSites()->getAllSites();

        $formatter = Craft::$app->getFormatter();

        $types = [
            'exact' => 'Exact match',
            'regexMatch' => 'Regex match',
        ];

        foreach ($query->all() as $row) {
            $lastHit = DateTimeHelper::toDateTime($row->dateLastHit);
            $row = [
                'url' => UrlHelper::cpUrl("seo-fields/redirects/edit/{$row->id}"),
                'id' => $row->id,
                'title' => $row->pattern,
                'redirect' => $row->redirect,
                'counter' => $row->counter,
                'site' => !$row->siteId ? "All" : Craft::$app->getSites()->getSiteById($row->siteId)->name,
                'lastHit' => $lastHit ? $formatter->asDatetime($lastHit, Locale::LENGTH_SHORT) : "",
                'method' => $row->method,
                'matchType' => $types[$row->matchType],
            ];

            $rows[] = $row;
        }
        $nextPageUrl = self::REDIRECT_BASE;
        $prevPageUrl = self::REDIRECT_BASE;

        $from = ($page - 1) * $limit + 1;
        $total = count($rows);
        $lastPage = (int) ceil($total / $limit);
        $to = $page === $lastPage ? $total : ($page * $limit);

        $rows = array_slice($rows, $from - 1, $limit);

        return $this->asJson([
            'pagination' => [
                'total' => (int)$total,
                'per_page' => (int)$limit,
                'current_page' => (int)$page,
                'last_page' => (int)$lastPage,
                'next_page_url' => $nextPageUrl,
                'prev_page_url' => $prevPageUrl,
                'from' => (int)$from,
                'to' => (int)$to,
            ],
            'data' => $rows,
        ]);
    }
}
