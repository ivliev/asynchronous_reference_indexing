<?php
namespace NamelessCoder\AsyncReferenceIndexing\Task;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use NamelessCoder\AsyncReferenceIndexing\Database\ReferenceIndex as AsyncReferenceIndex;
use NamelessCoder\AsyncReferenceIndexing\Traits\ReferenceIndexQueueAware;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\ReferenceIndex;

class AsyncReferenceIndexTask extends \TYPO3\CMS\Scheduler\Task\AbstractTask {
    use ReferenceIndexQueueAware;
    
    const LOCKFILE = 'typo3temp/var/reference-indexing-running.lock';
    
    /**
     * @var \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface
     * @TYPO3\CMS\Extbase\Annotation\Inject
     */
    protected $configurationManager;
    
    public function execute() {
        $count = $this->performCount('tx_asyncreferenceindexing_queue');
        
        if (!$count) {
            return TRUE;
        }
        
        // Note about loop: a fresh instance of ReferenceIndex is *intentional*. The class mutates
        // internal state during processing. Furthermore, we catch any errors and exit only after
        // removing the lock file. Any error causes processing to stop completely.
        try {
            
            // Force the reference index override to disable capturing. Will apply to *all* instances
            // of ReferenceIndex (but of course only when the override gets loaded).
            AsyncReferenceIndex::captureReferenceIndex(false);
            
            foreach ($this->getRowsWithGenerator('tx_asyncreferenceindexing_queue') as $queueItem) {
                
                /** @var $referenceIndex ReferenceIndex */
                $referenceIndex = GeneralUtility::makeInstance(ReferenceIndex::class);
                if (!empty($queueItem['reference_workspace']) && BackendUtility::isTableWorkspaceEnabled($queueItem['reference_table'])) {
                    $referenceIndex->setWorkspaceId($queueItem['reference_workspace']);
                }
                $referenceIndex->updateRefIndexTable($queueItem['reference_table'], $queueItem['reference_uid']);
                $this->performDeletion(
                    'tx_asyncreferenceindexing_queue',
                    sprintf(
                        'reference_table = \'%s\' AND reference_uid = %d AND reference_workspace = %d',
                        (string) $queueItem['reference_table'],
                        (integer) $queueItem['reference_uid'],
                        (integer) $queueItem['reference_workspace']
                        )
                    );
                
            }
            
        } catch (\Exception $error) {
            
        }
        
        return TRUE;
    }
}