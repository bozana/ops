<?php

/**
 * @file classes/preprint/PreprintGalley.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PreprintGalley
 * @ingroup preprint
 *
 * @see PreprintGalleyDAO
 *
 * @brief A galley is a final presentation version of the full-text of an preprint.
 */

namespace APP\preprint;

use APP\core\Application;

use APP\facades\Repo;
use PKP\facades\Locale;
use PKP\submission\Representation;

class PreprintGalley extends Representation
{
    /** @var SubmissionFile */
    public $_submissionFile;


    //
    // Get/set methods
    //
    /**
     * Get views count.
     *
     * @deprecated 3.4
     *
     * @return int
     */
    public function getViews()
    {
        $views = 0;
        $submissionFileId = $this->getFileId();
        $filters = [
            'dateStart' => StatisticsHelper::STATISTICS_EARLIEST_DATE,
            'dateEnd' => date('Y-m-d', strtotime('yesterday')),
            'contextIds' => [Application::get()->getRequest()->getContext()->getId()],
            'submissionFileIds' => [$submissionFileId],
        ];
        $metrics = Services::get('publicationStats')
            ->getQueryBuilder($filters)
            ->getSum([])
            ->get()->toArray();
        if (!empty($metrics)) {
            $views = (int) current($metrics)->metric;
        }
        return $views;
    }

    /**
     * Get label/title.
     *
     * @return string
     */
    public function getLabel()
    {
        return $this->getData('label');
    }

    /**
     * Set label/title.
     *
     * @param string $label
     */
    public function setLabel($label)
    {
        return $this->setData('label', $label);
    }

    /**
     * Get locale.
     *
     * @return string
     */
    public function getLocale()
    {
        return $this->getData('locale');
    }

    /**
     * Set locale.
     *
     * @param string $locale
     */
    public function setLocale($locale)
    {
        return $this->setData('locale', $locale);
    }

    /**
     * Return the "best" preprint ID -- If a public preprint ID is set,
     * use it; otherwise use the internal preprint Id.
     *
     * @return string
     */
    public function getBestGalleyId()
    {
        return $this->getData('urlPath')
            ? $this->getData('urlPath')
            : $this->getId();
    }

    /**
     * Set file ID.
     *
     * @deprecated 3.3
     *
     * @param int $submissionFileId
     */
    public function setFileId($submissionFileId)
    {
        $this->setData('submissionFileId', $submissionFileId);
    }

    /**
     * Get file id
     *
     * @deprecated 3.3
     *
     * @return int
     */
    public function getFileId()
    {
        return $this->getData('submissionFileId');
    }

    /**
     * Get the submission file corresponding to this galley.
     *
     * @deprecated 3.3
     *
     * @return SubmissionFile
     */
    public function getFile()
    {
        if (!isset($this->_submissionFile)) {
            $this->_submissionFile = Repo::submissionFile()->get((int) $this->getFileId());
        }

        return $this->_submissionFile;
    }

    /**
     * Get the file type corresponding to this galley.
     *
     * @deprecated 3.3
     *
     * @return string MIME type
     */
    public function getFileType()
    {
        $galleyFile = $this->getFile();
        return $galleyFile ? $galleyFile->getData('mimetype') : null;
    }

    /**
     * Determine whether the galley is a PDF.
     *
     * @return bool
     */
    public function isPdfGalley()
    {
        return $this->getFileType() == 'application/pdf';
    }

    /**
     * Get the localized galley label.
     *
     * @return string
     */
    public function getGalleyLabel()
    {
        $label = $this->getLabel();
        if ($this->getLocale() != Locale::getLocale()) {
            $label .= ' (' . Locale::getMetadata($this->getLocale())->getDisplayName() . ')';
        }
        return $label;
    }

    /**
     * @see Representation::getName()
     *
     * This override exists to provide a functional getName() in order to make
     * native XML export work correctly.  It is only used in that single instance.
     *
     * @param string $locale unused, except to match the function prototype in Representation.
     *
     * @return array
     */
    public function getName($locale)
    {
        return [$this->getLocale() => $this->getLabel()];
    }

    /**
     * Override the parent class to fetch the non-localized label.
     *
     * @see Representation::getLocalizedName()
     *
     * @return string
     */
    public function getLocalizedName()
    {
        return $this->getLabel();
    }

    /**
     * @copydoc \PKP\submission\Representation::setStoredPubId()
     */
    public function setStoredPubId($pubIdType, $pubId)
    {
        if ($pubIdType == 'doi') {
            if ($doiObject = $this->getData('doiObject')) {
                Repo::doi()->edit($doiObject, ['doi' => $pubId]);
            } else {
                $newDoiObject = Repo::doi()->newDataObject(
                    [
                        'doi' => $pubId,
                        'contextId' => $this->getContextId()
                    ]
                );
                $doiId = Repo::doi()->add($newDoiObject);

                $this->setData('doiId', $doiId);
            }
        } else {
            parent::setStoredPubId($pubIdType, $pubId);
        }
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\APP\preprint\PreprintGalley', '\PreprintGalley');
}
