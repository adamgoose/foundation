<?php namespace Orchestra\Foundation\Processor;

use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Orchestra\Foundation\Presenter\User as UserPresenter;
use Orchestra\Foundation\Validation\User as UserValidator;
use Orchestra\Model\User as Eloquent;
use Orchestra\Support\Facades\App;

class User extends AbstractableProcessor
{
    /**
     * Create a new processor instance.
     *
     * @param  \Orchestra\Foundation\Presenter\User     $presenter
     * @param  \Orchestra\Foundation\Validation\User    $validator
     */
    public function __construct(UserPresenter $presenter, UserValidator $validator)
    {
        $this->presenter = $presenter;
        $this->validator = $validator;
    }

    /**
     * View list users page.
     *
     * @param  object  $listener
     * @param  string  $searchKeyword
     * @param  array   $searchRoles
     * @return mixed
     */
    public function index($listener, $searchKeyword = '', array $searchRoles = array())
    {
        // Get Users (with roles) and limit it to only 30 results for
        // pagination. Don't you just love it when pagination simply works.
        $eloquent = App::make('orchestra.user')->search($searchKeyword, $searchRoles)->paginate();
        $roles = App::make('orchestra.role')->lists('name', 'id');

        // Build users table HTML using a schema liked code structure.
        $table = $this->presenter->table($eloquent);

        Event::fire('orchestra.list: users', array($eloquent, $table));

        // Once all event listening to `orchestra.list: users` is executed,
        // we can add we can now add the final column, edit and delete
        // action for users.
        $this->presenter->actions($table);

        $data = array(
            'eloquent' => $eloquent,
            'roles'    => $roles,
            'table'    => $table,
        );

        return $listener->indexSucceed($data);
    }

    /**
     * View create user page.
     *
     * @param  object  $listener
     * @return mixed
     */
    public function create($listener)
    {
        $eloquent = App::make('orchestra.user');
        $form = $this->presenter->form($eloquent, 'create');

        $this->fireEvent('form', array($eloquent, $form));

        return $listener->createSucceed(compact('eloquent', 'form'));
    }

    /**
     * View edit user page.
     *
     * @param  object          $listener
     * @param  string|integer  $id
     * @return mixed
     */
    public function edit($listener, $id)
    {
        $eloquent = App::make('orchestra.user')->findOrFail($id);
        $form = $this->presenter->form($eloquent, 'update');

        $this->fireEvent('form', array($eloquent, $form));

        return $listener->editSucceed(compact('eloquent', 'form'));
    }

    /**
     * Store a user.
     *
     * @param  object  $listener
     * @param  array   $input
     * @return mixed
     */
    public function store($listener, array $input)
    {
        $validation = $this->validator->on('create')->with($input);

        if ($validation->fails()) {
            return $listener->storeValidationFailed($validation);
        }

        $user = App::make('orchestra.user');

        $user->status = Eloquent::UNVERIFIED;
        $user->password = $input['password'];

        try {
            $this->saving($user, $input, 'create');
        } catch (Exception $e) {
            return $listener->storeFailed(array('error' => $e->getMessage()));
        }

        return $listener->storeSucceed();
    }

    /**
     * Update a user.
     *
     * @param  object          $listener
     * @param  string|integer  $id
     * @param  array           $input
     * @return mixed
     */
    public function update($listener, $id, array $input)
    {
        // Check if provided id is the same as hidden id, just a pre-caution.
        if ((string) $id !== $input['id']) {
            return $listener->userVerificationFailed();
        }

        $validation = $this->validator->on('update')->with($input);

        if ($validation->fails()) {
            return $listener->updateValidationFailed($validation, $id);
        }

        $user = App::make('orchestra.user')->findOrFail($id);

        ! empty($input['password']) and $user->password = $input['password'];

        try {
            $this->saving($user, $input, 'update');
        } catch (Exception $e) {
            return $listener->updateFailed(array('error' => $e->getMessage()));
        }

        return $listener->updateSucceed();
    }

    /**
     * Destroy a user.
     *
     * @param  object          $listener
     * @param  string|integer  $id
     * @return mixed
     */
    public function destroy($listener, $id)
    {
        $user = App::make('orchestra.user')->findOrFail($id);

        // Avoid self-deleting accident.
        if ((string) $user->id === (string) Auth::user()->id) {
            return $listener->selfDeletionFailed();
        }

        try {
            $this->fireEvent('deleting', array($user));

            DB::transaction(function () use ($user) {
                $user->delete();
            });

            $this->fireEvent('deleted', array($user));
        } catch (Exception $e) {
            return $listener->destroyFailed(array('error' => $e->getMessage()));
        }

        return $listener->destroySucceed();
    }

    /**
     * Save the user.
     *
     * @param  Orchestra\Model\User    $user
     * @param  array                   $input
     * @param  string                  $type
     * @return boolean
     */
    protected function saving(Eloquent $user, $input = array(), $type = 'create')
    {
        $beforeEvent = ($type === 'create' ? 'creating' : 'updating');
        $afterEvent  = ($type === 'create' ? 'created' : 'updated');

        $user->fullname = $input['fullname'];
        $user->email    = $input['email'];

        $this->fireEvent($beforeEvent, array($user));
        $this->fireEvent('saving', array($user));

        DB::transaction(function () use ($user, $input) {
            $user->save();
            $user->roles()->sync($input['roles']);
        });

        $this->fireEvent($afterEvent, array($user));
        $this->fireEvent('saved', array($user));

        return true;
    }

    /**
     * Fire Event related to eloquent process.
     *
     * @param  string  $type
     * @param  array   $parameters
     * @return void
     */
    protected function fireEvent($type, array $parameters = array())
    {
        Event::fire("orchestra.{$type}: users", $parameters);
        Event::fire("orchestra.{$type}: user.account", $parameters);
    }
}
