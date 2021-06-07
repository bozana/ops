<?php

/**
 * @file controllers/grid/settings/sections/SectionGridHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SectionGridHandler
 * @ingroup controllers_grid_settings_section
 *
 * @brief Handle section grid requests.
 */

import('lib.pkp.controllers.grid.settings.SetupGridHandler');
import('controllers.grid.settings.sections.SectionGridRow');

use APP\facades\Repo;
use APP\notification\NotificationManager;
use PKP\controllers\grid\feature\OrderGridItemsFeature;
use PKP\controllers\grid\GridColumn;
use PKP\core\JSONMessage;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;

use PKP\security\Role;

class SectionGridHandler extends SetupGridHandler
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->addRoleAssignment(
            [Role::ROLE_ID_MANAGER],
            ['fetchGrid', 'fetchRow', 'addSection', 'editSection', 'updateSection', 'deleteSection', 'saveSequence', 'deactivateSection','activateSection']
        );
    }


    //
    // Overridden template methods
    //
    /**
     * @copydoc SetupGridHandler::initialize()
     *
     * @param null|mixed $args
     */
    public function initialize($request, $args = null)
    {
        parent::initialize($request, $args);
        $server = $request->getServer();

        // FIXME are these all required?
        AppLocale::requireComponents(
            LOCALE_COMPONENT_APP_MANAGER,
            LOCALE_COMPONENT_PKP_COMMON,
            LOCALE_COMPONENT_PKP_USER,
            LOCALE_COMPONENT_APP_COMMON
        );

        // Set the grid title.
        $this->setTitle('section.sections');

        // Elements to be displayed in the grid
        $sectionDao = DAORegistry::getDAO('SectionDAO'); /* @var $sectionDao SectionDAO */
        $subEditorsDao = DAORegistry::getDAO('SubEditorsDAO');
        $sectionIterator = $sectionDao->getByServerId($server->getId());

        $gridData = [];
        while ($section = $sectionIterator->next()) {
            // Get the section editors data for the row
            $assignedSubEditors = $subEditorsDao->getBySubmissionGroupId($section->getId(), ASSOC_TYPE_SECTION, $server->getId());
            if (empty($assignedSubEditors)) {
                $editorsString = __('common.none');
            } else {
                $editors = [];
                foreach ($assignedSubEditors as $subEditor) {
                    $editors[] = $subEditor->getFullName();
                }
                $editorsString = implode(', ', $editors);
            }

            $sectionId = $section->getId();
            $gridData[$sectionId] = [
                'title' => $section->getLocalizedTitle(),
                'editors' => $editorsString,
                'inactive' => $section->getIsInactive(),
                'seq' => $section->getSequence()
            ];
        }
        uasort($gridData, function ($a, $b) {
            return $a['seq'] - $b['seq'];
        });

        $this->setGridDataElements($gridData);

        // Add grid-level actions
        $router = $request->getRouter();
        $this->addAction(
            new LinkAction(
                'addSection',
                new AjaxModal(
                    $router->url($request, null, null, 'addSection', null, ['gridId' => $this->getId()]),
                    __('manager.sections.create'),
                    'modal_manage'
                ),
                __('manager.sections.create'),
                'add_section'
            )
        );

        //
        // Grid columns.
        //
        import('controllers.grid.settings.sections.SectionGridCellProvider');
        $sectionGridCellProvider = new SectionGridCellProvider();

        // Section name
        $this->addColumn(
            new GridColumn(
                'title',
                'common.title'
            )
        );
        // Section 'editors'
        $this->addColumn(new GridColumn('editors', 'user.role.editors'));
        //Section 'inactive'
        $this->addColumn(
            new GridColumn(
                'inactive',
                'common.inactive',
                null,
                'controllers/grid/common/cell/selectStatusCell.tpl',
                $sectionGridCellProvider,
                ['alignment' => GridColumn::COLUMN_ALIGNMENT_CENTER,
                    'width' => 20]
            )
        );
    }

    //
    // Overridden methods from GridHandler
    //
    /**
     * @copydoc GridHandler::initFeatures()
     */
    public function initFeatures($request, $args)
    {
        return [new OrderGridItemsFeature()];
    }

    /**
     * Get the row handler - override the default row handler
     *
     * @return SectionGridRow
     */
    protected function getRowInstance()
    {
        return new SectionGridRow();
    }

    /**
     * @copydoc GridHandler::getDataElementSequence()
     */
    public function getDataElementSequence($row)
    {
        return $row['seq'];
    }

    /**
     * @copydoc GridHandler::setDataElementSequence()
     */
    public function setDataElementSequence($request, $rowId, $gridDataElement, $newSequence)
    {
        $sectionDao = DAORegistry::getDAO('SectionDAO'); /* @var $sectionDao SectionDAO */
        $server = $request->getServer();
        $section = $sectionDao->getById($rowId, $server->getId());
        $section->setSequence($newSequence);
        $sectionDao->updateObject($section);
    }

    //
    // Public Section Grid Actions
    //
    /**
     * An action to add a new section
     *
     * @param $args array
     * @param $request PKPRequest
     */
    public function addSection($args, $request)
    {
        // Calling editSection with an empty ID will add
        // a new section.
        return $this->editSection($args, $request);
    }

    /**
     * An action to edit a section
     *
     * @param $args array
     * @param $request PKPRequest
     *
     * @return string Serialized JSON object
     * @return JSONMessage JSON object
     */
    public function editSection($args, $request)
    {
        $sectionId = $args['sectionId'] ?? null;
        $this->setupTemplate($request);

        import('controllers.grid.settings.sections.form.SectionForm');
        $sectionForm = new SectionForm($request, $sectionId);
        $sectionForm->initData();
        return new JSONMessage(true, $sectionForm->fetch($request));
    }

    /**
     * Update a section
     *
     * @param $args array
     * @param $request PKPRequest
     *
     * @return JSONMessage JSON object
     */
    public function updateSection($args, $request)
    {
        $sectionId = $request->getUserVar('sectionId');

        import('controllers.grid.settings.sections.form.SectionForm');
        $sectionForm = new SectionForm($request, $sectionId);
        $sectionForm->readInputData();

        if ($sectionForm->validate()) {
            $sectionForm->execute();
            $notificationManager = new NotificationManager();
            $notificationManager->createTrivialNotification($request->getUser()->getId());
            return DAO::getDataChangedEvent($sectionForm->getSectionId());
        }
        return new JSONMessage(false);
    }

    /**
     * Delete a section
     *
     * @param $args array
     * @param $request PKPRequest
     *
     * @return JSONMessage JSON object
     */
    public function deleteSection($args, $request)
    {
        $server = $request->getServer();

        $sectionDao = DAORegistry::getDAO('SectionDAO'); /* @var $sectionDao SectionDAO */
        $section = $sectionDao->getById(
            $request->getUserVar('sectionId'),
            $server->getId()
        );

        if (!$request->checkCSRF()) {
            return new JSONMessage(false, __('form.csrfInvalid'));
        }

        if (!$section) {
            return new JSONMessage(false, __('manager.setup.errorDeletingItem'));
        }

        AppLocale::requireComponents(LOCALE_COMPONENT_PKP_MANAGER);
        $checkSubmissions = Repo::submission()->dao->deprecatedDao->retrieve('SELECT p.publication_id FROM publications p JOIN submissions s ON (s.submission_id = p.submission_id) WHERE p.section_id = ? AND s.context_id = ?', [(int) $request->getUserVar('sectionId'), (int) $server->getId()]);

        if ($checkSubmissions->numRows() > 0) {
            return new JSONMessage(false, __('manager.sections.alertDelete'));
        }

        // Validate if it can be deleted
        $sectionsIterator = $sectionDao->getByContextId($server->getId(), null, false);
        $activeSectionsCount = (!$section->getIsInactive()) ? -1 : 0;
        while ($checkSection = $sectionsIterator->next()) {
            if (!$checkSection->getIsInactive()) {
                $activeSectionsCount++;
            }
        }

        if ($activeSectionsCount < 1) {
            return new JSONMessage(false, __('manager.sections.confirmDeactivateSection.error'));
            return false;
        }

        if ($checkSubmissions->numRows() > 0) {
            return new JSONMessage(false, __('manager.sections.alertDelete'));
        }

        $sectionDao->deleteObject($section);
        return DAO::getDataChangedEvent($section->getId());
    }


    /**
     * Deactivate a section.
     *
     * @param $args array
     * @param $request PKPRequest
     *
     * @return JSONMessage JSON object
     */
    public function deactivateSection($args, $request)
    {
        // Identify the current section
        $sectionId = (int) $request->getUserVar('sectionKey');

        // Identify the context id.
        $context = $request->getContext();

        // Get section object
        $sectionDao = DAORegistry::getDAO('SectionDAO'); /* @var $sectionDao SectionDAO */
        // Validate if it can be inactive
        $sectionsIterator = $sectionDao->getByContextId($context->getId(), null, false);
        $activeSectionsCount = 0;
        while ($section = $sectionsIterator->next()) {
            if (!$section->getIsInactive()) {
                $activeSectionsCount++;
            }
        }
        if ($activeSectionsCount > 1) {
            $section = $sectionDao->getById($sectionId, $context->getId());

            if ($request->checkCSRF() && isset($section) && !$section->getIsInactive()) {
                $section->setIsInactive(1);
                $sectionDao->updateObject($section);

                // Create the notification.
                $notificationMgr = new NotificationManager();
                $user = $request->getUser();
                $notificationMgr->createTrivialNotification($user->getId());

                return DAO::getDataChangedEvent($sectionId);
            }
        } else {
            // Create the notification.
            $notificationMgr = new NotificationManager();
            $user = $request->getUser();
            $notificationMgr->createTrivialNotification($user->getId(), NOTIFICATION_TYPE_ERROR, ['contents' => __('manager.sections.confirmDeactivateSection.error')]);
            return DAO::getDataChangedEvent($sectionId);
        }

        return new JSONMessage(false);
    }

    /**
     * Activate a section.
     *
     * @param $args array
     * @param $request PKPRequest
     *
     * @return JSONMessage JSON object
     */
    public function activateSection($args, $request)
    {

        // Identify the current section
        $sectionId = (int) $request->getUserVar('sectionKey');

        // Identify the context id.
        $context = $request->getContext();

        // Get section object
        $sectionDao = DAORegistry::getDAO('SectionDAO'); /* @var $sectionDao SectionDAO */
        $section = $sectionDao->getById($sectionId, $context->getId());

        if ($request->checkCSRF() && isset($section) && $section->getIsInactive()) {
            $section->setIsInactive(0);
            $sectionDao->updateObject($section);

            // Create the notification.
            $notificationMgr = new NotificationManager();
            $user = $request->getUser();
            $notificationMgr->createTrivialNotification($user->getId());

            return DAO::getDataChangedEvent($sectionId);
        }

        return new JSONMessage(false);
    }
}
