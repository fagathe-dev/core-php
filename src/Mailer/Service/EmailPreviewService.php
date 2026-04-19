<?php

namespace Fagathe\CorePhp\Mailer\Service;

use Fagathe\CorePhp\Mailer\Enum\EmailTypeEnum;
use Twig\Environment as Twig;

class EmailPreviewService
{
    public function __construct(
        private Twig $twig,
        private MailerService $mailerService,
        private EmailMockFactory $mockFactory
    ) {
    }

    /**
     * @param EmailTypeEnum $type
     * 
     * @return string
     */
    public function preview(EmailTypeEnum $type): string
    {
        $mockEmail = $this->mockFactory->create($type);
        $context = [...$this->mailerService->getDefaultContext(), ...$mockEmail->getContext()];

        return $this->twig->render(
            $mockEmail->getTemplate(),
            $context
        );
    }
}
