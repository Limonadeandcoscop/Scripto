<?php
namespace Scripto\Controller\PublicApp;

use Laminas\View\Model\ViewModel;
use Laminas\Mvc\Controller\AbstractActionController;
use Scripto\Api\Representation\ScriptoItemRepresentation;

class ItemController extends AbstractActionController
{
    public function browseAction()
    {
        $project = $this->scripto()->getRepresentation($this->params('project-id'));
        if (!$project) {
            return $this->redirect()->toRoute('scripto');
        }

        $this->setBrowseDefaults('id');
        $query = $this->params()->fromQuery();
        $query['scripto_project_id'] = $this->params('project-id');
        if ($project->filterApproved()
            && !isset($query['is_approved'])
            && !isset($query['is_not_approved'])
            && !isset($query['is_in_progress'])
            && !isset($query['is_new'])
            && !isset($query['is_edited_after_imported'])
        ) {
            $query['is_not_approved'] = true;
        }
        $response = $this->api()->search('scripto_items', $query);
        $this->paginator($response->getTotalResults(), $this->params()->fromQuery('page'));
        $sItems = $response->getContent();

        $view = new ViewModel;
        $view->setVariable('sItems', $sItems);
        $view->setVariable('project', $project);
        $this->layout()->setVariable('project', $project);
        return $view;
    }


    public function browseAllAction()
    {
        $this->setBrowseDefaults('id');
        $query = $this->params()->fromQuery();
        $per_page = $this->settings()->get('pagination_per_page');

        $page = $query['page']; // Retrieve requested page
        unset($query['page']); // Delete 'page' param from query (in order to retrieve all items)

        $response = $this->api()->search('scripto_items', $query); // Get all items
        $total = $response->getTotalResults(); // Total number of items
        $this->paginator($total, $page); // Enable pagination

        $start = $per_page * ($page-1);

        // Redirect if the requested page doesn't exist
        if ($start >= $total) {
            return $this->redirect()->toRoute('scripto-all-items');
        }

        $sItems = $response->getContent();

        // Order results
        $results = [];
        foreach ($sItems as $item) {

            $response = $this->api()->search('scripto_media', ['scripto_item_id' => $item->id()]);
            $sMedias = $response->getContent();
            $nbMedias = $item->mediaCount();

            $mediasStatus = [];
            if (count($sMedias)) {
                foreach($sMedias as $sMedia) {
                    @$mediasStatus[$sMedia->status()]++;
                }
            }
            $nbInProgress = @$mediasStatus[ScriptoItemRepresentation::STATUS_IN_PROGRESS];
            $percentInProgress = $nbMedias == 0 ?  0 : $nbInProgress / $nbMedias * 100;

            array_push($results, [  'scripto_item'      => $item,
                                    'percent_in_progress'         => $percentInProgress
                                ]);
        }

        // Order results by percent edited desc
        array_multisort( array_column($results, "percent_in_progress"), SORT_ASC, $results );

        unset($sItems);

        foreach($results as $result) {
            $sItems[] = $result['scripto_item'];
        }

        // Slide results for pagination
        $sItems = array_slice($sItems, $start, $per_page);
        $view = new ViewModel;
        $view->setVariable('sItems', $sItems);
        return $view;
    }

    public function showAction()
    {
        return $this->redirect()->toRoute(
            'scripto-media',
            ['action' => 'browse'],
            ['query' => $this->params()->fromQuery()],
            true
        );
    }
}
