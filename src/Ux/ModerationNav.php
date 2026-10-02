<?php

declare(strict_types=1);

namespace Kopling\SportsManagement\Ux;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\View\Component;
use Kopling\Core\Ux\Context;

class ModerationNav extends Component
{
    public function __construct(
        protected Request $request,
        public array $data = [],
        public ?Context $context = null,
    ) {
    }

    public function render(): View
    {
        return view('kopling-sports-management::ux.moderation-nav', [
            'active' => $this->request->routeIs('kopling-moderation::moderation/kopling-sports-management.teams'),
        ]);
    }
}
