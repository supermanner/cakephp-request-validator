<?php
namespace RequestValidator\Form;

use RequestValidator\Form\Exception\ValidationException;
use Cake\Form\Form;
use Cake\Http\Exception\BadRequestException;
use Cake\Utility\Hash;
use Cake\Validation\Validator;

/**
 * Validation Form
 *
 * 便宜的にRequest DataをValidateしてErrorを返すrequest Form
 */
class ValidationForm extends Form
{
    /* @const ネストされていると判断するルールセットの階層数 */
    const NESTED_RULE_THRESHOLD = 3;
    /* @const ネストされていると判断するエラーパラメータの階層数 */
    const NESTED_ERROR_THRESHOLD = 2;

    /** @var Validator validator */
    private $myValidator;
    /** @var array extraData */
    private $extraData;
    /** @var callable errorHandler */
    private $errorHandler = null;

    /**
     * ValidationForm constructor.
     *
     * @param array $settings ルールとメッセージ
     * @param array|null $extraData レスポンスに含めるresourceName. Nestしている場合は.でつなげる
     * @param callable|null $errorHandler errorHandler
     */
    public function __construct(array $settings, array $extraData = null, callable $errorHandler = null)
    {
        parent::__construct();
        $this->myValidator = new Validator();
        $this->extraData = $extraData;
        $this->errorHandler = $errorHandler;
        foreach ($settings as $paramName => $validationSetting) {
            $this->adaptAppValidator($paramName, $validationSetting);
        }

        $this->setValidator('default', $this->myValidator);
    }

    /**
     * Defines what to execute once the From is being processed
     *
     * @param array $data Form data.
     * @return void
     * @throws BadRequestException
     */
    protected function _execute(array $data)
    {
        $errorMessages = ['Request Validation Error.'];
        foreach ($this->getErrors() as $field => $error) {
            if ($this->errorHandler) {
                call_user_func($this->errorHandler, $field, $error, $this->extraData);
            } else {
                $validationErrorMessages = Hash::flatten($error);
                $validationErrorMessages= array_flip($validationErrorMessages);
                $validationErrorMessages= array_keys($validationErrorMessages);
                foreach ($validationErrorMessages as $message) {
                    $errorMessages[] = "[{$field}]: {$message}";
                }
            }
        }

        throw new ValidationException(implode("\n", $errorMessages));
    }

    /**
     * execute methodのoverride
     * 用途としてはvalidationに引っかかったら_executeを実行してほしい
     *
     * @param array $data ポストされてくるデータ
     * @return bool
     * @throws ValidationException _execute内部で投げられる可能性あり
     */
    public function execute(array $data)
    {
        if (!$this->validate($data)) {
            $this->_execute($data);
        }

        return true;
    }

    /**
     * バリデータの作成を担うメソッド
     *
     * @param Validator $validator 条件を付与していくバリデータ
     * @param string $paramName バリデーションを施すパラメータ名
     * @param array $rules ルールセットとバリデーションメッセージが入っている
     * @return Validator 条件を付与したバリデータ
     */
    private function buildAppValidator($validator, $paramName, $rules)
    {
        foreach ($rules as $ruleName => $properties) {
            $message = $properties['message'];
            $option = $properties['option'] ?? null;
            $when = $properties['when'] ?? null;
            $validator = $this->switchAlias($validator, $paramName, $ruleName, $message, $option, $when);
        }

        return $validator;
    }

    /**
     * 渡ってきたparameterからvalidatorを作成するmethod
     *
     * @param string $paramName パラメータ名
     * @param array $validationSetting パラメータにかませるValidationRuleの設定
     * @return void
     */
    private function adaptAppValidator($paramName, $validationSetting)
    {
        if (!$this->isNestedValidations($validationSetting)) {
            $this->myValidator = $this->buildAppValidator($this->myValidator, $paramName, $validationSetting['rules']);

            return;
        }

        $nestedValidator = new Validator();
        foreach ($validationSetting as $childParam => $childSettings) {
            $nestedValidator = $this->buildAppValidator($nestedValidator, $childParam, $childSettings['rules']);
        }
        $this->myValidator->addNestedMany($paramName, $nestedValidator);
    }

    /**
     * validationRuleの階層を数える
     *
     * @param array $validationSettings 渡されたルールセット
     * @return bool 渡されたリソースがネストしているかどうか
     */
    private function isNestedValidations($validationSettings)
    {
        $flatten = Hash::flatten($validationSettings);
        foreach ($flatten as $key => $val) {
            if (strpos($key, '.option.') !== false) {
                unset($flatten[$key]);
            }
        }
        $validationSettings = Hash::expand($flatten);

        return Hash::maxDimensions($validationSettings) > self::NESTED_RULE_THRESHOLD;
    }

    /**
     * validationのaliasから, 作るべきValidatorを作ってセットする
     *
     * @param Validator $validator セットするValidator
     * @param string $paramName パラメータ名
     * @param string $rule validationRule
     * @param string $message エラーメッセージ
     * @param array|bool $option validatorにセットするオプション
     * @param callable|bool|null $when 実行タイミング
     * @return  Validator セットされたValidator
     */
    private function switchAlias($validator, $paramName, $rule, $message, $option, $when)
    {
        if ($rule === 'require') {
            // パラメータ必須だがempty許すパターンはrequestには無いため一緒に定義
            $isRequirePresence = $when ?? true;
            $validator->requirePresence($paramName, $isRequirePresence, $message);

            $isNotEmpty =is_callable($when) ? $when : !$isRequirePresence;
            $validator->notEmpty($paramName, $message, $isNotEmpty);

            return $validator;
        }

        if ($option) {
            $ruleSet = [$rule];
            if (is_array($option)) {
                $ruleSet = array_merge($ruleSet, $option);
            } else {
                $ruleSet[] = $option;
            }
        } else {
            $ruleSet = $rule;
        }

        $validator->add(
            $paramName,
            "{$paramName}.{$rule}",
            ['rule' => $ruleSet, 'message' => $message, 'on' => $when]
        );

        return $validator;
    }
}
