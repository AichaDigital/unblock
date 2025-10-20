<?php

namespace App\Actions;

/**
 * @method static \Lorisleiva\Actions\Decorators\JobDecorator|\Lorisleiva\Actions\Decorators\UniqueJobDecorator makeJob(array $params, ?\Illuminate\Console\Command $command = null)
 * @method static \Lorisleiva\Actions\Decorators\UniqueJobDecorator makeUniqueJob(array $params, ?\Illuminate\Console\Command $command = null)
 * @method static \Illuminate\Foundation\Bus\PendingDispatch dispatch(array $params, ?\Illuminate\Console\Command $command = null)
 * @method static \Illuminate\Foundation\Bus\PendingDispatch|\Illuminate\Support\Fluent dispatchIf(bool $boolean, array $params, ?\Illuminate\Console\Command $command = null)
 * @method static \Illuminate\Foundation\Bus\PendingDispatch|\Illuminate\Support\Fluent dispatchUnless(bool $boolean, array $params, ?\Illuminate\Console\Command $command = null)
 * @method static dispatchSync(array $params, ?\Illuminate\Console\Command $command = null)
 * @method static dispatchNow(array $params, ?\Illuminate\Console\Command $command = null)
 * @method static dispatchAfterResponse(array $params, ?\Illuminate\Console\Command $command = null)
 * @method static void run(array $params, ?\Illuminate\Console\Command $command = null)
 */
class WhmcsSynchro
{
}
namespace Lorisleiva\Actions\Concerns;

/**
 * @method void asController()
 */
trait AsController
{
}
/**
 * @method void asListener()
 */
trait AsListener
{
}
/**
 * @method void asJob()
 */
trait AsJob
{
}
/**
 * @method void asCommand(\Illuminate\Console\Command $command)
 */
trait AsCommand
{
}