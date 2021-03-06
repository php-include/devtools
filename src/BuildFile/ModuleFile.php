<?php

declare(strict_types=1);
/*
 * PHP version 7.1
 *
 * @copyright Copyright (c) 2012-2017 EELLY Inc. (https://www.eelly.com)
 * @link      https://api.eelly.com
 * @license   衣联网版权所有
 */

namespace Eelly\DevTools\BuildFile;

use Eelly\Acl\Adapter\Database;
use Phalcon\Db\Adapter\Pdo\Mysql;

/**
 * Module生成类
 *
 * @author eellytools<localhost.shell@gmail.com>
 */
class ModuleFile extends File
{
    /**
     * 模块目录.
     *
     * @var string
     */
    protected $moduleDir = '';

    /**
     * 模块生成的目录信息.
     *
     * @var array
     */
    protected $moduleDirInfo = [];

    /**
     * 模块名称.
     *
     * @var string
     */
    protected $moduleName = '';

    /**
     * 模块构建.
     *
     * @param string $moduleName
     *
     * @return array
     */
    public function run(string $moduleName): array
    {
        $oauthDb = $this->config->oauthDb->toArray();
        $this->di->setShared('oauthDb', function () use($oauthDb){
            return $db = new Mysql($oauthDb);
        });
        $this->di->setShared('eellyAcl', [
            'className' => Database::class,
            "properties" => [
                [
                    "name" => "db",
                    "value" => [
                        "type" => "service",
                        "name" => "oauthDb"
                    ]
                ]
            ]

        ]);

        $this->setModuleName($moduleName);
        $this->insertModuleAndClient();

        return $this->buildModuleDir();
    }

    /**
     * 返回模块生成的目录信息.
     *
     * @return array
     */
    public function returnModuleDirInfo(): array
    {
        return [
            'moduleDir' => $this->moduleDir,
            'moduleDirInfo' => $this->moduleDirInfo,
        ];
    }

    /**
     * 设置模块名.
     *
     * @param string $moduleName
     */
    private function setModuleName(string $moduleName): void
    {
        $this->moduleName = $moduleName;
    }

    /**
     * 模块目录构建.
     *
     * @return array
     */
    private function buildModuleDir(): array
    {
        $moduleName = ucfirst($this->moduleName);
        $this->moduleDir = $this->baseDir.$moduleName;

        if (!is_dir($this->moduleDir)) {
            mkdir($this->moduleDir, 0755);
        }

        // 生成模块文件
        $this->buildModuleFile();
        // 生成模块子目录
        $this->buildChildDir();
        // 生成api
        $this->buildModuleApi();
        // 模块下model生成
        $this->buildModuleModel();
        // 生成模块配置文件
        $this->buildConfigDir();

        return $this->returnModuleDirInfo();
    }

    /**
     * 构建模块文件.
     */
    private function buildModuleFile(): void
    {
        $filePath = $this->moduleDir.'/Module'.$this->fileExt;
        !file_exists($filePath) && file_put_contents($filePath, $this->getModuleFileCode());
    }

    /**
     * 获取模块文件code.
     *
     * @return string
     */
    private function getModuleFileCode(): string
    {
        $templates = file_get_contents($this->templateDir.'BaseTemplate.php');

        $namespace = $this->getNamespace(ucfirst($this->moduleName));
        $useNamespace = $this->getUseNamespace(['Eelly\Mvc\AbstractModule']);
        $className = $this->getClassName('Module', 'AbstractModule');
        $properties = [
            'NAMESPACE' => [
                'type' => 'const',
                'qualifier' => 'public',
                'value' => '__NAMESPACE__',
            ],
            'NAMESPACE_DIR' => [
                'type' => 'const',
                'qualifier' => 'public',
                'value' => '__DIR__',
            ],
        ];
        $properties = $this->getClassProperties($properties);
        $body = $this->getClassBody();

        return sprintf($templates, $namespace, $useNamespace, $className, $properties, $body);
    }

    /**
     * 生成子目录.
     */
    private function buildChildDir(): void
    {
        $child = [
            'Model.Mysql',
            'Model.MongoDB',
            'Logic',
        ];

        foreach ($child as $dirName) {
            if (false !== strpos($dirName, '.')) {
                $dirInfo = explode('.', $dirName);
                $dirName = $dirInfo[1];
                $dirPath = $this->moduleDir.'/'.$dirInfo[0].'/'.$dirInfo[1];
            } else {
                $dirPath = $this->moduleDir.'/'.$dirName;
            }
            $this->moduleDirInfo[$dirName]['path'] = $dirPath;
            $this->moduleDirInfo[$dirName]['namespace'] = ltrim(str_replace('/', '\\', $dirPath), 'src/\\');
            if (!is_dir($dirPath)) {
                mkdir($dirPath, 0755, true);
            }
        }
    }

    /**
     * 获取类的主体.
     *
     * @return string
     */
    private function getClassBody(): string
    {
        return <<<EOF
    /**
     * {@inheritdoc}
     *
     * @see \Eelly\Mvc\AbstractModule::registerUserAutoloaders()
     */
    public function registerUserAutoloaders(\Phalcon\DiInterface \$dependencyInjector = null): void
    {
    }

    /**
     * {@inheritdoc}
     *
     * @see \Eelly\Mvc\AbstractModule::registerUserServices()
     */
    public function registerUserServices(\Phalcon\DiInterface \$dependencyInjector): void
    {
    }
EOF;
    }

    /**
     * 模块model生成.
     */
    private function buildModuleModel(): void
    {
        (new ModelFile($this->di))->run($this->moduleName, $this->moduleDirInfo);
    }

    /**
     * 模块配置文件生成.
     */
    private function buildConfigDir(): void
    {
        (new ConfigFile($this->di))->run($this->moduleName);
    }

    /**
     * 模块api生成
     */
    private function buildModuleApi(): void
    {
        (new ApiFile($this->di))->run($this->moduleName, $this->moduleDirInfo['Logic']);
    }

    private function insertModuleAndClient(): void
    {
        $this->eellyAcl->addModule($this->moduleName);
        $this->eellyAcl->addModuleClient($this->moduleName);
        $this->eellyAcl->addRole($this->moduleName, null, $this->moduleName . '/*/*');
        $this->eellyAcl->addRoleClient($this->moduleName, $this->eellyAcl->getClientKeyByModuleName($this->moduleName));
    }
}
