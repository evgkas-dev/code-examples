<?php

namespace App\Services;

use App\Models\AtURL\Slave\Url as SlaveAtUrl;
use App\Models\AaURL\Url;
use App\Models\TrainingBadUrl;
use App\Models\TrainingCategoryUrl;
use App\Models\TrainingParkedUrl;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use AtURL\HtmlAnalysis\Libs\Parser;
use AtURL\Shared\Libs\HTTPRequest;

class TrainingService
{
    const URL_ACQUIRE_MINUTES = 10;

    public $trainingClass;

    /**
     * Acquire URL for processing by user
     * @param $urlId
     * @param $userId
     * @return bool
     */
    public function acquireUrl($urlId, $userId)
    {
        $trainingUrlModel = $this->getTrainingModel($urlId, $userId);
        if(!$trainingUrlModel) {
            return false;
        }

        $trainingUrlModel->cur_user_id = $userId;
        $trainingUrlModel->session_expire_in = Carbon::now()->addMinutes(self::URL_ACQUIRE_MINUTES);
        $trainingUrlModel->save();

        return true;
    }

    /**
     * Return available URL model for training
     *
     * @return Url|null
     */
    public function getNextAvailableUrlModel(): ?SlaveAtUrl
    {
        $trainingUrl = $this->trainingClass::whereNull('user_id')
            ->where(function ($query) {
                $query
                    ->where(function ($query) {
                        $query
                            ->where('cur_user_id', '<>', Auth::id())
                            ->where('session_expire_in', '<', Carbon::now());
                    })
                    ->orWhere('cur_user_id', Auth::id());
            })
            ->first();

        $query = SlaveAtUrl::select(['id', 'url']);

        if ($trainingUrl) {
            $urlModel = $query->find($trainingUrl->url_id);
        } else {
            $lastTrainingUrlId = $this->trainingClass::max('url_id');
            $query->where('id', '>', $lastTrainingUrlId ?? 0);

            if (!$lastTrainingUrlId) {
                $query->where('created', '>', Carbon::now()->subDays(70));
            }
            $urlModel = $query->first();
        }

        return $urlModel;
    }

    /**
     * Return URL model for training
     *
     * @param int|null $id
     * @return Url|null
     */
    public function getUrlModel(int $id=null): ?SlaveAtUrl
    {
        if($id) {
            return SlaveAtUrl::find($id);
        }

        return $this->getNextAvailableUrlModel();
    }

        /**
     * @param string $type
     * @return TrainingService
     * @throws \RuntimeException
     */
    public function setTrainingClass(string $type): self
    {
        switch ($type) {
            case 'park':
                $this->trainingClass = TrainingParkedUrl::class;
                break;
            case 'bad':
                $this->trainingClass = TrainingBadUrl::class;
                break;
            case 'category':
                $this->trainingClass = TrainingCategoryUrl::class;
                break;
            default:
                throw new \RuntimeException("Wrong training type");
        }

        return $this;
    }

    /**
     * return URL metadata
     *
     * @param $url
     * @return Collection
     * @throws \Throwable
     */
    public function getUrlMetaData($url): Collection
    {
        return new Collection(
            (new Parser)
                ->parse($url, HTTPRequest::curl($url))
                ->getAttributes()
        );
    }

    /**
     * url processing
     *
     * @param int $userId
     * @param int $urlId
     * @param mixed $value
     * @return bool
     */
    public function urlProcessing($userId, $urlId, $value)
    {
        $trainingUrlModel = $this->getTrainingModel($urlId, $userId);
        if(!$trainingUrlModel) {
            return false;
        }

        if ($trainingUrlModel instanceof TrainingCategoryUrl) {
            $trainingUrlModel->category_id = $value;
        } else {
            $trainingUrlModel->status = $value;
        }

        $trainingUrlModel->user_id = $userId;
        $trainingUrlModel->save();

        return true;
    }

    private function getTrainingModel($urlId, $userId)
    {
        $trainingUrlModel = $this->trainingClass::firstOrNew(['url_id' => $urlId]);

        if ($trainingUrlModel->exists) {
            $isAcquiredByUser = $trainingUrlModel->cur_user_id == $userId;
            $isExpired = Carbon::parse($trainingUrlModel->session_expire_in)->lessThan(Carbon::now());
            if (!$isAcquiredByUser && !$isExpired) {
                return null;
            }
        }

        return $trainingUrlModel;
    }

}