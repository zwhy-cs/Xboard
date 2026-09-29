<?php

namespace Tests\Unit\Protocols;

use App\Http\Requests\Admin\ConfigSave;
use App\Protocols\ClashMeta;
use App\Support\Setting;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class ClashMetaSubscriptionRuleTest extends TestCase
{
    private Container $previousContainer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousContainer = Container::getInstance();
        Container::setInstance(new Container());
        app()->instance('config', new Repository());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        Container::setInstance($this->previousContainer);
        parent::tearDown();
    }

    #[DataProvider('policies')]
    public function test_subscription_rule($setting, ?string $host, ?string $expectedRule): void
    {
        $settings = Mockery::mock(Setting::class);
        $settings->shouldReceive('get')->twice()->with('subscribe_domain_rule')->andReturn($setting);
        app()->instance(Setting::class, $settings);
        $request = Request::create('/');
        $request->headers->remove('Host');
        if ($host !== null) {
            $request->headers->set('Host', $host);
        }
        app()->instance('request', $request);

        // buildRules does not need server filtering or plugin/database bootstrapping.
        $protocol = (new ReflectionClass(ClashMeta::class))->newInstanceWithoutConstructor();
        $original = [
            'proxies' => [['name' => '链式节点', 'dialer-proxy' => '机场前置']],
            'proxy-groups' => [['name' => '机场前置', 'use' => ['provider']]],
            'rules' => ['DOMAIN,subscription.example,DIRECT', 'MATCH,PROXY'],
        ];
        $expected = $original;
        if ($expectedRule !== null) {
            array_unshift($expected['rules'], $expectedRule);
        }
        $this->assertSame($expected, $protocol->buildRules($original));

        // Empty/missing template rules must work, and OFF must not create rules.
        $withoutRules = ['proxies' => []];
        $expected = $withoutRules;
        if ($expectedRule !== null) {
            $expected['rules'] = [$expectedRule];
        }
        $this->assertSame($expected, $protocol->buildRules($withoutRules));
    }

    public static function policies(): array
    {
        return [
            'unset keeps fork default' => [null, 'subscription.example', 'DOMAIN,subscription.example,PROXY'],
            'direct' => ['DIRECT', 'subscription.example', 'DOMAIN,subscription.example,DIRECT'],
            'proxy' => ['PROXY', 'subscription.example', 'DOMAIN,subscription.example,PROXY'],
            'off preserves template rules' => ['OFF', 'subscription.example', null],
            'missing host' => ['PROXY', null, null],
            'empty host' => ['DIRECT', '', null],
            'host with port' => ['DIRECT', 'subscription.example:8443', 'DOMAIN,subscription.example,DIRECT'],
            'invalid stored value' => ['REJECT', 'subscription.example', 'DOMAIN,subscription.example,PROXY'],
            'invalid stored type' => [false, 'subscription.example', 'DOMAIN,subscription.example,PROXY'],
        ];
    }

    #[DataProvider('configValues')]
    public function test_admin_validation(array $data, bool $passes): void
    {
        $factory = new Factory(new Translator(new ArrayLoader(), 'en'));
        $validator = $factory->make($data, ConfigSave::RULES);
        $this->assertSame($passes, $validator->passes());
        if ($passes) {
            $this->assertSame($data, $validator->validated());
        }
    }

    public static function configValues(): array
    {
        return [
            'older clients omit setting' => [[], true],
            'direct' => [['subscribe_domain_rule' => 'DIRECT'], true],
            'proxy' => [['subscribe_domain_rule' => 'PROXY'], true],
            'off' => [['subscribe_domain_rule' => 'OFF'], true],
            'null' => [['subscribe_domain_rule' => null], false],
            'empty' => [['subscribe_domain_rule' => ''], false],
            'lowercase' => [['subscribe_domain_rule' => 'proxy'], false],
            'unknown policy' => [['subscribe_domain_rule' => 'REJECT'], false],
            'boolean' => [['subscribe_domain_rule' => false], false],
            'array' => [['subscribe_domain_rule' => ['PROXY']], false],
        ];
    }
}
