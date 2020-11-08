<?php

namespace Storj\Uplink\Test;

use RuntimeException;
use Storj\Uplink\Access;
use Storj\Uplink\Exception\Bucket\BucketNotEmpty;
use Storj\Uplink\ListObjectsOptions;
use Storj\Uplink\Project;
use Storj\Uplink\Uplink;

class Util
{
    private static ?Access $access = null;

    private static ?Project $project = null;

    public static function getSatelliteAddress(): string
    {
        if (getenv('SATELLITE_ADDRESS')) {
            return getenv('SATELLITE_ADDRESS');
        }

        // exported by storj-sim
        if (getenv('SATELLITE_0_ID') && getenv('SATELLITE_0_ADDR')) {
            return getenv('SATELLITE_0_ID') . '@' . getenv('SATELLITE_0_ADDR');
        }

        throw new RuntimeException('SATELLITE_ADDRESS not set');
    }

    public static function access(): Access
    {
        if (!self::$access) {
            self::$access = Uplink::create()->requestAccessWithPassphrase(
                self::getSatelliteAddress(),
                getenv('GATEWAY_0_API_KEY'),
                'mypassphrase'
            );
        }

        return self::$access;
    }

    public static function emptyProject(): Project
    {
        $project = self::project();
        self::wipeProject($project);
        return $project;
    }

    public static function project(): Project
    {
        if (!self::$project) {
            self::$project = self::access()->openProject();
        }

        return self::$project;
    }

    public static function emptyAccess(): Access
    {
        $project = self::project();

        self::wipeProject($project);

        return self::$access;
    }

    private static function wipeProject(Project $project): void
    {
        foreach ($project->listBuckets() as $bucket) {
            $bucketName = $bucket->getName();

            foreach ($project->listObjects($bucketName, (new ListObjectsOptions())->withRecursive()) as $objectInfo) {
                $project->deleteObject($bucketName, $objectInfo->getKey());
            }

            try {
                $project->deleteBucket($bucket->getName());
            } catch (BucketNotEmpty $e) {
                // https://github.com/storj/storj/issues/3922
            }
        }
    }
}
