<?php

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Mockery as m;
use Mpociot\Versionable\Version;
use Mpociot\Versionable\VersionableTrait;
use Tests\Stubs\User;
use function Opis\Closure\unserialize;

class ApprovalsTest extends VersionableTestCase
{
    public function setUp()
    {
        parent::setUp();

        $initialDispatcher = Event::getFacadeRoot();
        Event::fake();
        Model::setEventDispatcher($initialDispatcher);
    }

    public function tearDown()
    {
        m::close();
        Auth::clearResolvedInstances();
    }

    public function mockUser()
    {
        $user = new User;
        $user->enableApprovals();
        $user->disableVersioning();

        $user->name     = 'Name Here';
        $user->email    = 'email@domain.com';
        $user->password = 'password';

        return $user;
    }

    public function test_when_approvals_is_created_model_should_not_be_saved_in_main_table()
    {
        $user = $this->mockUser();
        $user->save();

        $this->assertCount(0, User::all());
        $this->assertCount(1, Version::all());
    }

    public function test_when_an_item_is_saved_it_should_contain_all_details_in_version()
    {
        $user = $this->mockUser();
        $user->save();

        $approval = Version::first();
        $data = unserialize($approval->model_data);
        
        $this->assertEquals('Name Here', $data['name']);
        $this->assertEquals('email@domain.com', $data['email']);
        $this->assertEquals('password', $data['password']);
    }

    public function test_a_new_item_should_contain_a_model_class()
    {
        $user = $this->mockUser();
        $user->save();

        $approval = Version::first();

        $this->assertEquals(User::class, $approval->versionable_type);
    }
    
    public function test_a_new_item_wont_have_an_id()
    {
        $user = $this->mockUser();
        $user->save();

        $approval = Version::first();

        $this->assertNull($approval->versionable_id);
    }

    public function test_a_new_item_will_fire_an_event()
    {
        $user = $this->mockUser();
        $user->save();

        Event::assertDispatched('eloquent.pendingApproval', function ($event, $model, $version) use ($user) {
            $data = unserialize($version->model_data);
            return (
                        (get_class($model) == $version->versionable_type) &&
                        ($version->versionable_id == null) &&
                        ($model->name == $data['name'])
                    );
        });
    }

    public function test_a_version_can_be_rejected()
    {
        $user = $this->mockUser();
        $user->save();

        $approval = Version::approvals()->first();

        $approval->reject();

        $this->assertCount(0, Version::approvals()->get());
    }

    public function test_when_a_version_is_rejected_it_is_not_stored()
    {
        $user = $this->mockUser();
        $user->save();

        $approval = Version::approvals()->first();

        $approval->reject();

        $this->assertCount(0, User::all());
    }    

    public function test_when_a_version_is_rejected_a_event_is_dispatched()
    {
        $user = $this->mockUser();
        $user->save();

        $approval = Version::approvals()->first();

        //Event::fake();

        $approval->reject();

        Event::assertDispatched('eloquent.rejected', function ($event, $model, $version) use ($user) {
            $data = unserialize($version->model_data);
            return (
                        (null == $version->versionable_type) &&
                        ($version->versionable_id == null) &&
                        ($model->name == $data['name'])
                    );
        });
    }

    public function test_when_a_version_is_rejected_it_can_be_retrived()
    {
        $user = $this->mockUser();
        $user->save();

        $approval = Version::approvals()->first();

        $approval->reject();

        $this->assertCount(1, Version::approvals('rejected')->get());
    }
}
