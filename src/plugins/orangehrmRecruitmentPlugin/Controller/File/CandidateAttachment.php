<?php
/**
 * OrangeHRM is a comprehensive Human Resource Management (HRM) System that captures
 * all the essential functionalities required for any enterprise.
 * Copyright (C) 2006 OrangeHRM Inc., http://www.orangehrm.com
 *
 * OrangeHRM is free software; you can redistribute it and/or modify it under the terms of
 * the GNU General Public License as published by the Free Software Foundation; either
 * version 2 of the License, or (at your option) any later version.
 *
 * OrangeHRM is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with this program;
 * if not, write to the Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor,
 * Boston, MA  02110-1301, USA
 */

namespace OrangeHRM\Recruitment\Controller\File;

use OrangeHRM\Core\Controller\AbstractFileController;
use OrangeHRM\Framework\Http\Request;
use OrangeHRM\Framework\Http\Response;
use OrangeHRM\Recruitment\Traits\Service\RecruitmentAttachmentServiceTrait;
use ZipArchive;

class CandidateAttachment extends AbstractFileController
{
    use RecruitmentAttachmentServiceTrait;

    public function handle(Request $request): Response
    {
        $candidateId = $request->attributes->get('candidateId');
        $response = $this->getResponse();

        if ($candidateId) {
            // instead of just one attachment we need to get all attachments
            $attachments = $this->getRecruitmentAttachmentService()
                ->getRecruitmentAttachmentDao()
                ->getCandidateAttachmentsByCandidateId($candidateId);
            if (count($attachments) === 1) {
                $attachment = $attachments[0];
                $this->setCommonHeadersToResponse(
                    $attachment->getFileName(),
                    $attachment->getFileType(),
                    $attachment->getFileSize(),
                    $response
                );
                $response->setContent($attachment->getDecorator()->getFileContent());
                return $response;
            } elseif (count($attachments) > 1) {
                // if there are more than one attachment we need to zip them
                while (ob_get_level()) {
                    ob_end_clean(); // remove output buffers
                }

                /** @var \OrangeHRM\Entity\Candidate $candidate */
                $candidate = $attachments[0]->getCandidate();
                $fileName = $candidate->getFirstName() . '_' . $candidate->getLastName() . '_attachments.zip';

                $response->headers->set('Content-Type', 'application/zip');
                $response->headers->set('Content-Disposition', 'attachment; filename="' . $fileName . '"');
                $response->headers->set('Pragma', 'public');
                $response->headers->set('Cache-Control', 'must-revalidate, post-check=0, pre-check=0');
                $response->headers->set('Expires', '0');
                $response->headers->set("Content-Transfer-Encoding", "binary");

                // create tmp zip file
                $tmpZipFile = tempnam(sys_get_temp_dir(), 'zip');
                // Create a new tmp zip archive
                $zip = new ZipArchive();
                $zip->open($tmpZipFile, ZipArchive::CREATE);
                $zip->setCompressionIndex(0, ZipArchive::CM_STORE); // no compression
                foreach ($attachments as $attachment) {
                    $fileContent = $attachment->getDecorator()->getFileContent();
                    $zip->addFromString($attachment->getFileName(), $fileContent);
                }

                $zip->setArchiveComment('Created on ' . date('Y-M-d'));
                $zip->close();
                // read tmp zip file and send it to the client
                $response->setContent(file_get_contents($tmpZipFile));
                unlink($tmpZipFile);
                return $response;
            }
        }
        return $this->handleBadRequest();
    }
}
