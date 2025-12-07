<?php

declare(strict_types=1);

namespace OCA\DocuSealIntegration\Command;

use OCA\DocuSealIntegration\Db\SigningSessionMapper;
use OCA\DocuSealIntegration\Service\MatrixService;
use OCA\DocuSealIntegration\Service\SignatureService;
use OCP\Files\IRootFolder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Sign extends Command
{
    public function __construct(
        private IRootFolder $rootFolder,
        private MatrixService $matrixService,
        private SignatureService $signatureService,
        private SigningSessionMapper $sessionMapper,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('docuseal:sign')
            ->setDescription('Create a Matrix signing session for a document')
            ->addArgument(
                'user',
                InputArgument::REQUIRED,
                'Nextcloud user ID who owns the file'
            )
            ->addArgument(
                'file',
                InputArgument::REQUIRED,
                'Path to the file in user\'s Nextcloud (e.g., /Documents/contract.pdf)'
            )
            ->addOption(
                'signer',
                's',
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Matrix user ID to invite as signer (can be used multiple times)',
                []
            )
            ->addOption(
                'ssh-key',
                'k',
                InputOption::VALUE_REQUIRED,
                'Path to SSH private key for immediate self-signing'
            )
            ->addOption(
                'gpg-key',
                'g',
                InputOption::VALUE_REQUIRED,
                'GPG key ID for immediate self-signing'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $userId = $input->getArgument('user');
        $filePath = $input->getArgument('file');
        $signers = $input->getOption('signer');
        $sshKeyPath = $input->getOption('ssh-key');
        $gpgKeyId = $input->getOption('gpg-key');

        try {
            // Get file from Nextcloud
            $userFolder = $this->rootFolder->getUserFolder($userId);

            if (!$userFolder->nodeExists($filePath)) {
                $output->writeln("<error>File not found: {$filePath}</error>");
                return Command::FAILURE;
            }

            $file = $userFolder->get($filePath);
            $fileContent = $file->getContent();
            $fileName = $file->getName();

            // Calculate document hash
            $documentHash = hash('sha256', $fileContent);
            $output->writeln("Document: <info>{$fileName}</info>");
            $output->writeln("SHA-256:  <comment>{$documentHash}</comment>");
            $output->writeln("");

            // If signers provided, create Matrix room
            if (!empty($signers)) {
                $output->writeln("Creating Matrix signing room...");

                $result = $this->matrixService->createSigningRoom(
                    $fileContent,
                    $fileName,
                    $signers,
                    $userId
                );

                // Store session
                $this->sessionMapper->create([
                    'user_id' => $userId,
                    'room_id' => $result['room_id'],
                    'document_name' => $fileName,
                    'document_hash' => $documentHash,
                    'mxc_uri' => $result['mxc_uri'],
                    'original_file_path' => $filePath,
                    'required_signers' => json_encode($signers),
                    'status' => 'pending',
                ]);

                $output->writeln("<info>Room created:</info> {$result['room_id']}");
                $output->writeln("");
                $output->writeln("Invited signers:");
                foreach ($signers as $signer) {
                    $output->writeln("  - {$signer}");
                }
            }

            // If SSH key provided, sign immediately
            if ($sshKeyPath) {
                $output->writeln("");
                $output->writeln("Signing with SSH key...");

                if (!file_exists($sshKeyPath)) {
                    $output->writeln("<error>SSH key not found: {$sshKeyPath}</error>");
                    return Command::FAILURE;
                }

                $signResult = $this->signatureService->signWithSsh(
                    $documentHash,
                    $sshKeyPath
                );

                $output->writeln("<info>Signature created!</info>");
                $output->writeln("");
                $output->writeln("Key type: {$signResult['key_type']}");
                $output->writeln("");
                $output->writeln("Public key:");
                $output->writeln("<comment>{$signResult['public_key']}</comment>");
                $output->writeln("");
                $output->writeln("Signature:");
                $output->writeln("<comment>{$signResult['signature']}</comment>");

                // If Matrix room exists, post signature
                if (!empty($signers) && isset($result['room_id'])) {
                    $output->writeln("");
                    $output->writeln("Posting signature to Matrix room...");
                    // TODO: Post to room
                }
            }

            // If GPG key provided, sign with GPG
            if ($gpgKeyId) {
                $output->writeln("");
                $output->writeln("Signing with GPG key {$gpgKeyId}...");

                $signResult = $this->signatureService->signWithGpg(
                    $documentHash,
                    $gpgKeyId
                );

                $output->writeln("<info>Signature created!</info>");
                $output->writeln("");
                $output->writeln("Signature:");
                $output->writeln("<comment>{$signResult['signature']}</comment>");
            }

            // Show signing instructions if no immediate signing
            if (!$sshKeyPath && !$gpgKeyId) {
                $output->writeln("");
                $output->writeln("<info>To sign this document:</info>");
                $output->writeln("");
                $output->writeln("With SSH key:");
                $output->writeln("  <comment>echo -n '{$documentHash}' | ssh-keygen -Y sign -f ~/.ssh/id_ed25519 -n document</comment>");
                $output->writeln("");
                $output->writeln("With GPG:");
                $output->writeln("  <comment>echo -n '{$documentHash}' | gpg --armor --detach-sign</comment>");
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $output->writeln("<error>Error: {$e->getMessage()}</error>");
            return Command::FAILURE;
        }
    }
}
