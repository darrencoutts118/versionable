<?php
namespace Mpociot\Versionable;

use Illuminate\Support\Facades\Auth;
use Event;

/**
 * Class VersionableTrait
 * @package Mpociot\Versionable
 */
trait VersionableTrait
{

    /**
     * Retrieve, if exists, the property that define that Version model.
     * If no property defined, use the default Version model.
     *
     * Trait cannot share properties whth their class !
     * http://php.net/manual/en/language.oop5.traits.php
     * @return unknown|string
     */
    protected function getVersionClass()
    {
        if (property_exists(self::class, 'versionClass')) {
            return $this->versionClass;
        }

        return config('versionable.version_model', Version::class);
    }

    /**
     * Private variable to detect if this is an update
     * or an insert
     * @var boolean
     */
    private $updating;

    /**
     * Contains all dirty data that is valid for versioning
     *
     * @var array
     */
    private $versionableDirtyData;

    /**
     * Optional reason, why this version was created
     * @var string
     */
    private $reason;

    /**
     * Flag that determines if the model allows versioning at all
     * @var boolean
     */
    protected $versioningEnabled = true;

    /**
     * Flag that determines if the model allows approvals at all
     * @var boolean
     */
    protected $approvalsEnabled = false;

    /**
     * @return $this
     */
    public function enableVersioning()
    {
        $this->versioningEnabled = true;
        return $this;
    }

    /**
     * @return $this
     */
    public function disableVersioning()
    {
        $this->versioningEnabled = false;
        return $this;
    }

    /**
     * @return $this
     */
    public function enableApprovals()
    {
        $this->approvalsEnabled = true;
        return $this;
    }

    /**
     * @return $this
     */
    public function disableApprovals()
    {
        $this->approvalsEnabled = false;
        return $this;
    }

    /**
     * Attribute mutator for "reason"
     * Prevent "reason" to become a database attribute of model
     *
     * @param string $value
     */
    public function setReasonAttribute($value)
    {
        $this->reason = $value;
    }

    /**
     * Initialize model events
     */
    public static function bootVersionableTrait()
    {
        static::saving(function ($model) {
            return $model->versionablePreSave();
        });

        static::saved(function ($model) {
            $model->versionablePostSave();
        });
    }

    /**
     * Return all versions of the model
     * @return MorphMany
     */
    public function versions()
    {
        return $this->morphMany($this->getVersionClass(), 'versionable')->where('version_type', 'revision');
    }

    /**
     * Return all versions of the model
     * @return MorphMany
     */
    public function approvals($type = 'pending')
    {
        return $this->morphMany($this->getVersionClass(), 'versionable')->where('version_type', $type);
    }

    /**
     * Returns the latest version available
     * @return Version
     */
    public function currentVersion()
    {
        $class = $this->getVersionClass();
        return $this->versions()->latest()->first();
    }

    /**
     * Returns the previous version
     * @return Version
     */
    public function previousVersion()
    {
        $class = $this->getVersionClass();
        return $this->versions()->latest()->limit(1)->offset(1)->first();
    }

    /**
     * Get a model based on the version id
     *
     * @param $version_id
     *
     * @return $this|null
     */
    public function getVersionModel($version_id)
    {
        $version = $this->versions()->where('version_id', '=', $version_id)->first();
        if (!is_null($version)) {
            return $version->getModel();
        }

        return null;
    }

    /**
     * Pre save hook to determine if versioning is enabled and if we're updating
     * the model
     * @return void
     */
    protected function versionablePreSave()
    {
        if ($this->approvalsEnabled === true) {
            $version = $this->saveVersion('pending');
            event('eloquent.pendingApproval', $this, $version);
            return false;
        }

        if ($this->versioningEnabled === true) {
            $this->versionableDirtyData = $this->getDirty();
            $this->updating             = $this->exists;
        }
    }

    /**
     * Save a new version.
     * @return void
     */
    protected function versionablePostSave()
    {
        /**
         * We'll save new versions on updating and first creation
         */
        if (( $this->versioningEnabled === true && $this->updating && $this->isValidForVersioning() ) ||
            ( $this->versioningEnabled === true && !$this->updating && !is_null($this->versionableDirtyData) && count($this->versionableDirtyData))
        ) {
            $this->saveVersion('revision');
            $this->purgeOldVersions();
        }
    }

    /**
     * Save a version of the model into the database
     *
     * @param string $version_type
     * @return Model
     */
    protected function saveVersion($version_type)
    {
        // Save a new version
        $class                     = $this->getVersionClass();
        $version                   = new $class();
        $version->versionable_id   = $this->getKey() ?? null;
        $version->versionable_type = get_class($this);
        $version->version_type     = $version_type;
        $version->user_id          = $this->getAuthUserId();
        $version->model_data       = serialize($this->getAttributes());

        if (!empty($this->reason)) {
            $version->reason = $this->reason;
        }

        $version->save();
        return $version;
    }

    /**
     * Delete old versions of this model when the reach a specific count.
     *
     * @return void
     */
    private function purgeOldVersions()
    {
        $keep = isset($this->keepOldVersions) ? $this->keepOldVersions : 0;
        $count = $this->versions()->count();

        if ((int) $keep > 0 && $count > $keep) {
            $oldVersions = $this->versions()
                ->latest()
                ->take($count)
                ->skip($keep)
                ->get()
                ->each(function ($version) {
                $version->delete();
            });
        }
    }

    /**
     * Determine if a new version should be created for this model.
     *
     * @return boolean
     */
    private function isValidForVersioning()
    {
        $dontVersionFields = isset($this->dontVersionFields) ? $this->dontVersionFields : [];
        $removeableKeys    = array_merge($dontVersionFields, [$this->getUpdatedAtColumn()]);

        if (method_exists($this, 'getDeletedAtColumn')) {
            $removeableKeys[] = $this->getDeletedAtColumn();
        }

        return ( count(array_diff_key($this->versionableDirtyData, array_flip($removeableKeys))) > 0 );
    }

    /**
     * @return integer|null
     */
    protected function getAuthUserId()
    {
        if (Auth::check()) {
            return Auth::id();
        }

        return null;
    }
}
