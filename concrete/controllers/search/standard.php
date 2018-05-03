<?php
namespace Concrete\Controller\Search;

use Concrete\Controller\Dialog\Search\AdvancedSearch;
use Concrete\Core\Controller\AbstractController;
use Concrete\Core\Entity\Search\SavedSearch;
use Concrete\Core\Search\Result\Result;
use Symfony\Component\HttpFoundation\JsonResponse;

abstract class Standard extends AbstractController
{

    abstract protected function canAccess();

    /**
     * @return AdvancedSearch
     */
    abstract protected function getAdvancedSearchDialogController();

    protected function getDefaultResetSearchResultObject()
    {
        return $this->getDefaultBasicSearchResultObject();
    }

    protected function getDefaultBasicSearchResultObject()
    {
        $advancedSearch = $this->getAdvancedSearchDialogController();
        $provider = $advancedSearch->getSearchProvider();
        $query = $advancedSearch->getSearchQuery();

        $set = $provider->getBaseColumnSet();
        $available = $provider->getAvailableColumnSet();

        if ($this->request->query->get('column')) {
          foreach ($this->request->query->get('column') as $key) {
              $set->addColumn($available->getColumnByKey($key));
          }
          $sort = $available->getColumnByKey($this->getRequestDefaultSort());
          $set->setDefaultSortColumn($sort, $this->getRequestDefaultSortDirection());
          $query->setColumns($set);
        } else {
          $query->setColumns($provider->getDefaultColumnSet());
        }

        $query->setFields($this->getBasicSearchFieldsFromRequest());
        $result = $provider->getSearchResultFromQuery($query);
        $result->setBaseURL($advancedSearch->getBasicSearchBaseURL());

        // The basic search result doesn't have a query attached to it, only advanced search and current
        // search requests do.
        $result->setQuery(null);

        return $result;
    }

    protected function getRequestDefaultSort()
    {
        return $this->request->query->get('fSearchDefaultSort');
    }

    protected function getRequestDefaultSortDirection()
    {
        return $this->request->query->get('fSearchDefaultSortDirection');
    }

    abstract protected function getSavedSearchPreset($presetID);

    protected function getBasicSearchFieldsFromRequest()
    {
        $advancedSearch = $this->getAdvancedSearchDialogController();
        $manager = $advancedSearch->getFieldManager();
        $fields = $manager->getFieldsFromRequest($this->request->query->all());
        return $fields;
    }

    public function searchBasic()
    {
        if ($this->canAccess()) {
            $result = $this->getDefaultBasicSearchResultObject();
            return new JsonResponse($result->getJSONObject());
        } else {
            $this->app->shutdown();
        }
    }

    public function clearSearch()
    {
        if ($this->canAccess()) {
            $advancedSearch = $this->getAdvancedSearchDialogController();
            $provider = $advancedSearch->getSearchProvider();
            $provider->clearSessionCurrentQuery();

            $result = $this->getDefaultResetSearchResultObject();
            return new JsonResponse($result->getJSONObject());
        } else {
            $this->app->shutdown();
        }
    }

    public function getCurrentSearchObject()
    {
        $advancedSearch = $this->getAdvancedSearchDialogController();
        $provider = $advancedSearch->getSearchProvider();
        $query = $provider->getSessionCurrentQuery();
        if (is_object($query)) {
            $result = $provider->getSearchResultFromQuery($query);
            $result->setBaseURL($advancedSearch->getCurrentSearchBaseURL());
        } else {
            $result = $this->getDefaultBasicSearchResultObject();
        }
        return $result;
    }

    public function searchCurrent()
    {
        if ($this->canAccess()) {
            $result = $this->getCurrentSearchObject();
            return new JsonResponse($result->getJSONObject());
        }
        $this->app->shutdown();
    }

    protected function onAfterSearchPreset(Result $result, SavedSearch $search)
    {
        return $result;
    }

    public function searchPreset($presetID)
    {
        if ($this->canAccess()) {
            $preset = $this->getSavedSearchPreset($presetID);
            if (is_object($preset)) {
                $query = $preset->getQuery();
                if (is_object($query)) {
                    $advancedSearch = $this->getAdvancedSearchDialogController();
                    $provider = $advancedSearch->getSearchProvider();
                    $result = $provider->getSearchResultFromQuery($query);
                    $result->setBaseURL($advancedSearch->getSavedSearchBaseURL($preset));

                    $result = $this->onAfterSearchPreset($result, $preset);

                    return new JsonResponse($result->getJSONObject());
                }
            }
        }

        $this->app->shutdown();
    }



}
