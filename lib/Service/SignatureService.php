<?php

declare(strict_types=1);

namespace OCA\DocuSealIntegration\Service;

use Psr\Log\LoggerInterface;

/**
 * SSH/GPG signature creation and verification
 *
 * Uses ssh-keygen for SSH signatures (OpenSSH 8.0+)
 * Uses gpg for GPG signatures
 */
class SignatureService
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Sign data with SSH key
     *
     * @param string $data The data to sign (usually a hash)
     * @param string $privateKeyPath Path to SSH private key
     * @param string $namespace Signature namespace (default: 'file')
     * @return array{signature: string, public_key: string, key_type: string}
     */
    public function signWithSsh(
        string $data,
        string $privateKeyPath,
        string $namespace = 'document'
    ): array {
        // Create temp file with data to sign
        $dataFile = tempnam(sys_get_temp_dir(), 'sign_data_');
        $sigFile = tempnam(sys_get_temp_dir(), 'sign_sig_');

        try {
            file_put_contents($dataFile, $data);

            // Sign with ssh-keygen
            $command = sprintf(
                'ssh-keygen -Y sign -f %s -n %s %s 2>&1',
                escapeshellarg($privateKeyPath),
                escapeshellarg($namespace),
                escapeshellarg($dataFile)
            );

            exec($command, $output, $returnCode);

            if ($returnCode !== 0) {
                throw new \RuntimeException('SSH signing failed: ' . implode("\n", $output));
            }

            // Signature is written to {dataFile}.sig
            $signature = file_get_contents($dataFile . '.sig');
            unlink($dataFile . '.sig');

            // Get public key
            $publicKey = $this->getPublicKeyFromPrivate($privateKeyPath);
            $keyType = $this->detectKeyType($publicKey);

            return [
                'signature' => $signature,
                'public_key' => $publicKey,
                'key_type' => $keyType,
            ];
        } finally {
            @unlink($dataFile);
            @unlink($sigFile);
        }
    }

    /**
     * Verify SSH signature
     */
    public function verifySshSignature(
        string $data,
        string $signature,
        string $publicKey,
        string $namespace = 'document'
    ): array {
        $dataFile = tempnam(sys_get_temp_dir(), 'verify_data_');
        $sigFile = tempnam(sys_get_temp_dir(), 'verify_sig_');
        $allowedSignersFile = tempnam(sys_get_temp_dir(), 'allowed_signers_');

        try {
            file_put_contents($dataFile, $data);
            file_put_contents($sigFile, $signature);

            // Create allowed_signers file
            // Format: principal key-type base64-key
            $keyParts = explode(' ', trim($publicKey));
            $keyType = $keyParts[0] ?? 'ssh-ed25519';
            $keyData = $keyParts[1] ?? '';

            file_put_contents(
                $allowedSignersFile,
                "signer@document {$publicKey}\n"
            );

            // Verify with ssh-keygen
            $command = sprintf(
                'ssh-keygen -Y verify -f %s -I %s -n %s -s %s < %s 2>&1',
                escapeshellarg($allowedSignersFile),
                escapeshellarg('signer@document'),
                escapeshellarg($namespace),
                escapeshellarg($sigFile),
                escapeshellarg($dataFile)
            );

            exec($command, $output, $returnCode);

            $valid = $returnCode === 0;

            return [
                'valid' => $valid,
                'key_type' => $keyType,
                'output' => implode("\n", $output),
            ];
        } finally {
            @unlink($dataFile);
            @unlink($sigFile);
            @unlink($allowedSignersFile);
        }
    }

    /**
     * Sign data with GPG key
     */
    public function signWithGpg(string $data, string $keyId): array
    {
        $dataFile = tempnam(sys_get_temp_dir(), 'gpg_data_');

        try {
            file_put_contents($dataFile, $data);

            // Create detached armored signature
            $command = sprintf(
                'gpg --armor --detach-sign --local-user %s %s 2>&1',
                escapeshellarg($keyId),
                escapeshellarg($dataFile)
            );

            exec($command, $output, $returnCode);

            if ($returnCode !== 0) {
                throw new \RuntimeException('GPG signing failed: ' . implode("\n", $output));
            }

            $signature = file_get_contents($dataFile . '.asc');
            unlink($dataFile . '.asc');

            // Get public key
            $publicKey = $this->exportGpgPublicKey($keyId);

            return [
                'signature' => $signature,
                'public_key' => $publicKey,
                'key_type' => 'gpg',
            ];
        } finally {
            @unlink($dataFile);
        }
    }

    /**
     * Verify GPG signature
     */
    public function verifyGpgSignature(
        string $data,
        string $signature,
        string $publicKey
    ): array {
        $dataFile = tempnam(sys_get_temp_dir(), 'gpg_verify_data_');
        $sigFile = tempnam(sys_get_temp_dir(), 'gpg_verify_sig_');
        $keyringDir = tempnam(sys_get_temp_dir(), 'gpg_keyring_');

        // Create temp directory for keyring
        unlink($keyringDir);
        mkdir($keyringDir, 0700);

        try {
            file_put_contents($dataFile, $data);
            file_put_contents($sigFile, $signature);

            // Import public key to temp keyring
            $keyFile = $keyringDir . '/pubkey.asc';
            file_put_contents($keyFile, $publicKey);

            $importCmd = sprintf(
                'gpg --homedir %s --import %s 2>&1',
                escapeshellarg($keyringDir),
                escapeshellarg($keyFile)
            );
            exec($importCmd, $importOutput, $importCode);

            // Verify signature
            $verifyCmd = sprintf(
                'gpg --homedir %s --verify %s %s 2>&1',
                escapeshellarg($keyringDir),
                escapeshellarg($sigFile),
                escapeshellarg($dataFile)
            );
            exec($verifyCmd, $verifyOutput, $verifyCode);

            $valid = $verifyCode === 0;

            // Extract signer info from output
            $signerInfo = $this->parseGpgVerifyOutput($verifyOutput);

            return [
                'valid' => $valid,
                'key_type' => 'gpg',
                'signer_info' => $signerInfo,
                'output' => implode("\n", $verifyOutput),
            ];
        } finally {
            @unlink($dataFile);
            @unlink($sigFile);
            $this->recursiveDelete($keyringDir);
        }
    }

    /**
     * Verify a signature (auto-detect type)
     */
    public function verifySignature(
        string $data,
        string $signature,
        string $publicKey,
        string $keyType
    ): array {
        if ($keyType === 'gpg') {
            return $this->verifyGpgSignature($data, $signature, $publicKey);
        } else {
            // SSH key types: ssh-ed25519, ssh-rsa, ecdsa-sha2-nistp256, etc.
            return $this->verifySshSignature($data, $signature, $publicKey);
        }
    }

    /**
     * Generate signing instructions for a document
     */
    public function getSigningInstructions(string $documentHash): array
    {
        return [
            'ssh' => [
                'description' => 'Sign with SSH key (recommended)',
                'command' => "echo -n '{$documentHash}' | ssh-keygen -Y sign -f ~/.ssh/id_ed25519 -n document -",
                'verify' => "Create an 'allowed_signers' file and use: ssh-keygen -Y verify -f allowed_signers -I signer -n document -s signature.sig < hash.txt",
            ],
            'gpg' => [
                'description' => 'Sign with GPG key',
                'command' => "echo -n '{$documentHash}' | gpg --armor --detach-sign",
                'verify' => "gpg --verify signature.asc hash.txt",
            ],
            'openssl' => [
                'description' => 'Sign with OpenSSL (Ed25519)',
                'command' => "echo -n '{$documentHash}' | openssl pkeyutl -sign -inkey private.pem | base64",
                'verify' => "echo -n 'hash' | openssl pkeyutl -verify -pubin -inkey public.pem -sigfile signature.bin",
            ],
        ];
    }

    /**
     * Hash a document for signing
     */
    public function hashDocument(string $content, string $algorithm = 'sha256'): string
    {
        return hash($algorithm, $content);
    }

    /**
     * Create a canonical signing payload
     */
    public function createSigningPayload(
        string $documentHash,
        string $documentName,
        int $timestamp,
        ?string $intent = null
    ): string {
        $payload = [
            'document_hash' => $documentHash,
            'document_name' => $documentName,
            'timestamp' => $timestamp,
            'intent' => $intent ?? 'I agree to the terms in this document',
        ];

        // Canonical JSON (sorted keys)
        ksort($payload);
        return json_encode($payload, JSON_UNESCAPED_SLASHES);
    }

    /**
     * Get public key from private key file
     */
    private function getPublicKeyFromPrivate(string $privateKeyPath): string
    {
        $command = sprintf(
            'ssh-keygen -y -f %s 2>&1',
            escapeshellarg($privateKeyPath)
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \RuntimeException('Failed to extract public key');
        }

        return trim(implode("\n", $output));
    }

    /**
     * Export GPG public key
     */
    private function exportGpgPublicKey(string $keyId): string
    {
        $command = sprintf(
            'gpg --armor --export %s 2>&1',
            escapeshellarg($keyId)
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \RuntimeException('Failed to export GPG public key');
        }

        return implode("\n", $output);
    }

    /**
     * Detect SSH key type from public key
     */
    private function detectKeyType(string $publicKey): string
    {
        $parts = explode(' ', trim($publicKey));
        return $parts[0] ?? 'unknown';
    }

    /**
     * Parse GPG verify output for signer info
     */
    private function parseGpgVerifyOutput(array $output): array
    {
        $info = [];

        foreach ($output as $line) {
            if (preg_match('/Good signature from "([^"]+)"/', $line, $matches)) {
                $info['signer'] = $matches[1];
            }
            if (preg_match('/using (\w+) key ([A-F0-9]+)/', $line, $matches)) {
                $info['key_algorithm'] = $matches[1];
                $info['key_id'] = $matches[2];
            }
        }

        return $info;
    }

    /**
     * Recursively delete a directory
     */
    private function recursiveDelete(string $path): void
    {
        if (is_dir($path)) {
            $files = array_diff(scandir($path), ['.', '..']);
            foreach ($files as $file) {
                $this->recursiveDelete($path . '/' . $file);
            }
            rmdir($path);
        } elseif (is_file($path)) {
            unlink($path);
        }
    }
}
