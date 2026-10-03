<?php

declare(strict_types=1);

namespace Kopling\SportsManagement;

use Kopling\Core\Extend\Icon;
use Kopling\Core\Extend\Model;
use Kopling\Core\Extend\ModerationTarget;
use Kopling\Core\Extend\Permission;
use Kopling\Core\Extend\Ux;
use Kopling\Core\Extend\Ux\ProvidesUxEntries;
use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\ChangesUx;
use Kopling\Core\Extension\Contract\ExtendsModels;
use Kopling\Core\Extension\Contract\ExtendsPortals;
use Kopling\Core\Extension\Contract\HasCommands;
use Kopling\Core\Extension\Contract\HasIcons;
use Kopling\Core\Extension\Contract\HasPermissions;
use Kopling\Core\Extension\Contract\HasPortals;
use Kopling\Core\Extension\Contract\RegistersModerationTargets;
use Kopling\Core\People\Person;
use Kopling\Core\Portal\Portal;
use Kopling\Core\Portal\PortalExtension;
use Kopling\Core\Ux\Community\UserMenu;
use Kopling\Core\Ux\Portal\Navigation\Item;
use Kopling\SportsManagement\Command\SeedKnhbFormatPresetsCommand;
use Kopling\SportsManagement\Command\SeedNbbFormatPresetsCommand;
use Kopling\SportsManagement\Command\SeedKnvbFormatPresetsCommand;
use Kopling\SportsManagement\Command\SeedNhvFormatPresetsCommand;
use Kopling\SportsManagement\Ux\MatchControls;
use Kopling\SportsManagement\Ux\ModerationNav;
use Kopling\SportsManagement\Ux\TeamsNav;

class Extension extends AbstractExtension implements ChangesUx, ExtendsModels, ExtendsPortals, HasCommands, HasIcons, HasPermissions, HasPortals, RegistersModerationTargets
{
    public const TEAM_CONTROL_SLOT = 'kopling-sports-management::team.control';

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
            new Icon(id: 'team', label: 'Team', default: 'fas-user-group'),
            new Icon(id: 'match', label: 'Match', default: 'fas-futbol'),
            new Icon(id: 'sports-management', label: 'Sports Management', default: 'fas-medal'),
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
            (new PortalExtension('kopling-moderation::moderation'))
                ->routes(__DIR__.'/../routes/moderation.php'),
        ];
    }

    /**
     * Roster members are children's records, not community members: no profile or other public page.
     *
     * @return array<Model>
     */
    public function models(): array
    {
        return [
            (new Model(Person::class))
                ->authorize('view', fn ($viewer, Person $person) => ! TeamMember::where('person_id', $person->id)->exists()),
        ];
    }

    /**
     * @return array<ModerationTarget>
     */
    public function moderationTargets(): array
    {
        return [
            new ModerationTarget(
                model: Team::class,
                label: __('kopling-sports-management::messages.moderation.team'),
                preview: 'kopling-sports-management::moderation.team-preview',
            ),
        ];
    }

    public function ux(): ProvidesUxEntries
    {
        $ux = Ux::make()
            ->add(UserMenu::class)
            ->in('kopling-sports-management::sports-management.topbar')
            ->as('user-menu')
            ->add(MatchControls::class)
            ->in('kopling-sports-management::sports-management.topbar-start')
            ->as('match-controls')
            ->add(TeamsNav::class)
            ->in('kopling-sports-management::sports-management.sidebar-panel')
            ->as('teams-nav')
            ->add(Item::class, [
                'label' => __('kopling-sports-management::messages.portal_label'),
                'route' => 'kopling-sports-management::sports-management/teams.index',
                'icon' => 'kopling-sports-management::sports-management',
                'hideOnPortal' => 'kopling-sports-management::sports-management',
            ])
            ->in(UserMenu::SLOT)
            ->as('portal-link')
            ->when('access-sports-management')
            ->priority(UserMenu::PRIORITY_TOP)
            ->add(ModerationNav::class)
            ->in('kopling-moderation::moderation.sidebar-panel')
            ->as('moderation-nav');

        if (class_exists(\Kopling\Moderation\Ux\ReportControlEntry::class)) {
            $ux->add(\Kopling\Moderation\Ux\ReportControlEntry::class)
                ->in(self::TEAM_CONTROL_SLOT)
                ->as('team-report');
        }

        return $ux;
    }

    /**
     * @return array<class-string>
     */
    public function commands(): array
    {
        return [SeedKnvbFormatPresetsCommand::class, SeedKnhbFormatPresetsCommand::class, SeedNhvFormatPresetsCommand::class, SeedNbbFormatPresetsCommand::class];
    }
}
