<?php

declare(strict_types=1);

namespace SupportKBZ\SObject\Concerns;

use Exception;

/**
 * Upload et telechargement de fichiers ContentVersion / ContentDocumentLink.
 */
trait UploadsAndDownloads
{
    /** @var array<string, string> MIME types autorises pour l'upload */
    protected array $mimes = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/jpg' => 'jpg',
        'image/png' => 'png',
    ];

    /**
     * Upload un fichier vers Salesforce et le lie a une entite.
     *
     * @param  string  $pathFile  Chemin local du fichier a uploader.
     * @param  string  $linkedEntityId  Id Salesforce de l'entite a lier.
     * @param  string|null  $fileName  Nom du fichier a envoyer (optionnel).
     */
    public function upload(string $pathFile, string $linkedEntityId, ?string $fileName = null): bool
    {
        // Verifier si le fichier existe
        if (! file_exists($pathFile)) {
            return false;
        }

        // Check si le fichier est un fichier autorise a etre envoye
        $mime = mime_content_type($pathFile);

        if ($mime === false || ! array_key_exists($mime, $this->mimes)) {
            return false;
        }

        $nameSource = pathinfo($pathFile, PATHINFO_FILENAME);

        if (empty($fileName)) {
            $fileName = $nameSource.'.'.$this->mimes[$mime];
        } else {
            // Check multiple point
            $split = explode('.', $fileName);

            if (count($split) > 2) {
                return false;
            }

            $nameSend = pathinfo($fileName, PATHINFO_FILENAME);
            $fileName = $nameSend.'.'.$this->mimes[$mime];
        }

        // Upload le ContentVersion
        $versionFile = $this->sendApiRequest($this->apiUrl('sobjects/ContentVersion'), 'POST', [
            'Title' => $fileName,
            'PathOnClient' => $fileName,
            'ContentLocation' => 'S',
            'VersionData' => base64_encode((string) file_get_contents($pathFile)),
            'OwnerId' => config('salesforce.owner_id'),
        ]);

        if (empty($versionFile['id'])) {
            return false;
        }

        // Recuperer le ContentDocumentId
        $soql = str_replace(' ', '+', 'SELECT Id, ContentDocumentId FROM ContentVersion WHERE Id = \''.addslashes($versionFile['id']).'\' LIMIT 1');
        $query = $this->sendApiRequest($this->apiUrl('query/?q='.$soql));

        $contentDocumentId = $query['records'][0]['ContentDocumentId'] ?? null;

        if (empty($contentDocumentId)) {
            return false;
        }

        // Creer le lien entre le ContentDocument et l'entite
        $resp = $this->sendApiRequest($this->apiUrl('sobjects/ContentDocumentLink'), 'POST', [
            'ContentDocumentId' => $contentDocumentId,
            'LinkedEntityId' => $linkedEntityId,
        ]);

        return is_array($resp) && array_key_exists('id', $resp);
    }

    /**
     * Telecharge un fichier ContentVersion depuis Salesforce.
     *
     * @param  string  $contentVersionId  Id du ContentVersion a telecharger.
     * @param  string  $output  Chemin de sortie local (sans extension).
     * @param  bool  $duplicate  Si true, cree aussi une copie _2.
     * @return string|null Chemin du fichier telecharge, ou null en cas d'echec.
     */
    public function download(string $contentVersionId, string $output, bool $duplicate = false): ?string
    {
        if (empty($this->access['instance_url'])) {
            return null;
        }

        $salesforceUrl = $this->apiUrl('sobjects/ContentVersion/'.$contentVersionId.'/VersionData');

        try {
            $response = $this->sendApiRequest($salesforceUrl, 'GET', null, true, $output);

            if ($response !== true && ! file_exists($output)) {
                return null;
            }

            $mime = mime_content_type($output);

            if ($mime === false || ! array_key_exists($mime, $this->mimes)) {
                unlink($output);

                return null;
            }

            $res = $output.'.'.$this->mimes[$mime];
            rename($output, $res);

            if ($duplicate) {
                copy($res, $output.'_2.'.$this->mimes[$mime]);
            }

            $this->retry = false;

            return $res;
        } catch (Exception $e) {
            $message = $e->getMessage();

            if (str_contains($message, 'INVALID_SESSION_ID') && ! $this->retry) {
                return $this->retry()->download($contentVersionId, $output, $duplicate);
            }

            if (file_exists($output)) {
                unlink($output);
            }
        }

        return null;
    }
}
