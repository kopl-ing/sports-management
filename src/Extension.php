<?php

declare(strict_types=1);

namespace Kopling\SportsManagement;

use Kopling\Core\Extend\Icon;
use Kopling\Core\Extend\Permission;
use Kopling\Core\Extend\Ux;
use Kopling\Core\Extend\Ux\ProvidesUxEntries;
use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\ChangesUx;
use Kopling\Core\Extension\Contract\ExtendsPortals;
use Kopling\Core\Extension\Contract\HasCommands;
use Kopling\Core\Extension\Contract\HasIcons;
use Kopling\Core\Extension\Contract\HasPermissions;
use Kopling\Core\Extension\Contract\HasPortals;
use Kopling\Core\Portal\Portal;
use Kopling\Core\Portal\PortalExtension;
use Kopling\Core\Ux\Community\UserMenu;
use Kopling\SportsManagement\Command\SeedKnvbFormatPresetsCommand;
use Kopling\SportsManagement\Ux\MatchControls;
use Kopling\SportsManagement\Ux\TeamsNav;

class Extension extends AbstractExtension implements ChangesUx, ExtendsPortals, HasCommands, HasIcons, HasPermissions, HasPortals
{
    public static function name(): string
    {
        return 'Sports Management';
    }

    public static function description(): string
    {
        return 'Manage a youth sports team -- roster, staff, match planning, and match tracking.';
    }

    /**
     * @return array<Permission>
     */
    public function icons(): array
    {
        return [
            new Icon(id: 'pause', label: 'Break', default: 'fas-pause'),
            new Icon(id: 'play', label: 'Continue', default: 'fas-play'),
            new Icon(id: 'stop', label: 'End match', default: 'fas-stop'),
            new Icon(id: 'available', label: 'Available', default: 'fas-check'),
            new Icon(id: 'maybe', label: 'Maybe', default: 'fas-question'),
            new Icon(id: 'absent', label: 'Absent', default: 'fas-xmark'),
        ];
    }

    public function permissions(): array
    {
        return [
            new Permission(
                id: 'access-sports-management',
                label: __('kopling-sports-management::permissions.access-sports-management.label'),
                description: __('kopling-sports-management::permissions.access-sports-management.description'),
            ),
            new Permission(
                id: 'manage-teams',
                label: __('kopling-sports-management::permissions.manage-teams.label'),
                description: __('kopling-sports-management::permissions.manage-teams.description'),
            ),
            new Permission(
                id: 'manage-matches',
                label: __('kopling-sports-management::permissions.manage-matches.label'),
                description: __('kopling-sports-management::permissions.manage-matches.description'),
            ),
            new Permission(
                id: 'track-matches',
                label: __('kopling-sports-management::permissions.track-matches.label'),
                description: __('kopling-sports-management::permissions.track-matches.description'),
            ),
        ];
    }

    /**
     * `label` is a plain string: portals resolve before lang files are registered.
     *
     * @return array<Portal>
     */
    public function portals(): array
    {
        return [
            new Portal(
                id: 'sports-management',
                label: 'Sports Management',
                path: 'sports-management',
                layout: 'kopling-sports-management::layouts.sports-management',
                permission: 'access-sports-management',
            ),
        ];
    }

    /**
     * @return array<PortalExtension>
     */
    public function extendsPortals(): array
    {
        return [
            (new PortalExtension('kopling-sports-management::sports-management'))
                ->routes(__DIR__.'/../routes/sports-management.php')
                ->js(__DIR__.'/../js/app.js'),
        ];
    }

    public function ux(): ProvidesUxEntries
    {
        return Ux::make()
            ->add(UserMenu::class)
            ->in('kopling-sports-management::sports-management.topbar')
            ->as('user-menu')
            ->add(MatchControls::class)
            ->in('kopling-sports-management::sports-management.topbar-start')
            ->as('match-controls')
            ->add(TeamsNav::class)
            ->in('kopling-sports-management::sports-management.sidebar-panel')
            ->as('teams-nav');
    }

    /**
     * @return array<class-string>
     */
    public function commands(): array
    {
        return [SeedKnvbFormatPresetsCommand::class];
    }
}
