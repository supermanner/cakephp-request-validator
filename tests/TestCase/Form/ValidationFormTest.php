<?php
namespace RequestValidator\TestCase\Form;

use Cake\Http\ServerRequest;
use Cake\TestSuite\TestCase;
use RequestValidator\Form\Exception\ValidationException;
use RequestValidator\Form\ValidationForm;

/**
 *  RequestValidation\ValidationForm Test Case
 *
 */
class ValidationFormTest extends TestCase
{
    /**
     * Test subject
     *
     * @var ValidationForm
     */
    public $ValidationForm;

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown()
    {
        parent::tearDown();
    }

    /**
     * aliasが正しい分岐でvalidatorを生成しているかをチェックする
     *
     * @dataProvider requestValidationDataProvider
     *
     * @expectedException \RequestValidator\Form\Exception\ValidationException
     * @param ServerRequest $request リクエストオブジェクト
     * @param array $rules バリデーションルール
     * @return void
     */
    public function testEffectValidationInAlias(ServerRequest $request, array $rules)
    {
        $validationForm = new ValidationForm($rules);
        $validationForm->execute($request->getData());
    }

    /**
     * validationする条件を付与した場合に正しくvalidatorが生成されているか
     *  / callbackからtrueが返る場合
     *
     * @expectedException \RequestValidator\Form\Exception\ValidationException
     * @dataProvider provideWhenTrue
     * @param bool|callable $when 実行タイミング
     * @return void
     */
    public function testEffectValidationWithWhenTrue($when)
    {
        $request = new ServerRequest(['post' => []]);
        $rules = [
            'name' => [
                'rules' => [
                    'require' => [
                        'message' => 'ユーザー名は入力必須です',
                        'when' => $when
                    ]
                ]
            ]
        ];
        $validationForm = new ValidationForm($rules);
        $validationForm->execute($request->getData());
    }

    /**
     * validationする条件を付与した場合に正しくvalidatorが生成されているか
     *  / callbackからfalseが返る場合
     *
     * @doesNotPerformAssertions
     * @dataProvider provideWhenFalse
     * @param bool|callable $when 実行タイミング
     * @return void
     */
    public function testEffectValidationWithWhenFalse($when)
    {
        $request = new ServerRequest(['post' => []]);
        $rules = [
            'name' => [
                'rules' => [
                    'require' => [
                        'message' => 'ユーザー名は入力必須です',
                        'when' => $when
                    ]
                ]
            ]
        ];
        $validationForm = new ValidationForm($rules);
        $validationForm->execute($request->getData());
    }

    /**
     * errorHandlerが正しく動いているかをテスト
     *
     * @expectedException \RequestValidator\Form\Exception\ValidationException
     * @expectedExceptionMessage name.ユーザー名は入力必須です.User
     * @return void
     */
    public function testUseExtraDataAndErrorHandler()
    {
        $request = new ServerRequest(['post' => []]);
        $rules = [
            'name' => [
                'rules' => [
                    'require' => [
                        'message' => 'ユーザー名は入力必須です',
                    ]
                ]
            ]
        ];
        $extraData = ['resourceName' => 'User'];
        $errorHandler = function ($field, $error, $extraData) {
            $errorMessage = current($error);
            $message = "{$field}.{$errorMessage}.{$extraData['resourceName']}";
            throw new ValidationException($message);
        };
        $validationForm = new ValidationForm($rules, $extraData, $errorHandler);
        $validationForm->execute($request->getData());
    }

    /**
     * whenがtrueとして判定される場合のデータプロバイダ
     *
     * @return array
     */
    public function provideWhenTrue()
    {
        return [
            'whenにbool値をセットするケース' => [true],
            'whenにcallbackをセットするケース' => [
                function () {
                    return true;
                }
            ]
        ];
    }

    /**
     * whenがfalseとして判定される場合のデータプロバイダ
     *
     * @return array
     */
    public function provideWhenFalse()
    {
        return [
            'whenにbool値をセットするケース' => [false],
            'whenにcallbackをセットするケース' => [
                function () {
                    return false;
                }
            ]
        ];
    }

    /**
     * 様々なリクエストをテストするためのデータプロバイダ
     *
     * @return array
     */
    public function requestValidationDataProvider()
    {
        return [
            [
                new ServerRequest(['post' => []]),
                [
                    'name' => [
                        'rules' => [
                            'require' => ['message' => 'ユーザ名は入力必須です'],
                            'maxLength' => [
                                'option' => 20,
                                'message' => '文字数は20文字以内で入力してください'
                            ]
                        ],
                    ]
                ],
                'User',
                [
                    'resource' => 'User',
                    'field' => 'name',
                    'code' => 104,
                    'message' => 'ユーザ名は入力必須です'
                ]
            ],
            [
                new ServerRequest([
                    'post' => [
                        'name' => '文字数がとってもとってもとってもとっても' .
                            'とっても多いっていう名前'
                    ]
                ]),
                [
                    'name' => [
                        'rules' => [
                            'require' => ['message' => 'ユーザ名は入力必須です'],
                            'maxLength' => [
                                'option' => 20,
                                'message' => '文字数は20文字以内で入力してください'
                            ],
                        ],
                    ]
                ],
                'User',
                [
                    'resource' => 'User',
                    'field' => 'name',
                    'code' => 105,
                    'message' => '文字数は20文字以内で入力してください'
                ]
            ],
            [
                new ServerRequest(['post' => ['between' => 'しにたみしょくどくしにたみしょくどうしにたみしょくどう']]),
                [
                    'between' => [
                        'rules' => [
                            'lengthBetween' => [
                                'option' => [5, 10],
                                'message' => '文字数は1文字以上10以内で入力してください'
                            ],
                        ],
                    ]
                ],
                'User',
                [
                    'resource' => 'User',
                    'field' => 'between',
                    'code' => 105,
                    'message' => '文字数は1文字以上10以内で入力してください'
                ]
            ],
            [
                new ServerRequest(['post' => ['market_id' => 'もじもじもじ']]),
                [
                    'market_id' => [
                        'rules' => [
                            'isInteger' => ['message' => 'market_idは整数値を入力してください']
                        ],
                    ]
                ],
                'User',
                [
                    'resource' => 'User',
                    'field' => 'market_id',
                    'code' => 105,
                    'message' => 'market_idは整数値を入力してください'
                ]
            ],
            [
                new ServerRequest([
                    'post' => [
                        'add_children' => [
                            [
                                'name' => 'イカイカイカ全然勝てないんゴゴゴゴゴ',
                                'sex' => 'hoge'
                            ],
                        ]
                    ]
                ]),
                [
                    'add_children' => [
                        'sex' => [
                            'rules' => [
                                'require' => ['message' => '追加するお子さんの性別は必須です'],
                                'isInteger' => ['message' => '追加するお子さんの性別は整数です']
                            ],
                        ],
                        'birthday' => [
                            'rules' => [
                                'require' => ['message' => '追加するお子さんの誕生日は必須です']
                            ],
                        ],
                    ]
                ],
                'User.Child',
                [
                    'resource' => 'Child',
                    'field' => 'sex',
                    'code' => 105,
                    'message' => '追加するお子さんの性別は整数です'
                ]
            ],
            [
                new ServerRequest([
                    'post' => [
                        'add_children' => [
                            ['birthday' => 'hoge'],
                        ]
                    ]
                ]),
                [
                    'add_children' => [
                        'sex' => [
                            'rules' => [
                                'require' => ['message' => '追加するお子さんの性別は必須です'],
                                'isInteger' => ['message' => '追加するお子さんの性別は整数です'],
                            ]
                        ],
                        'birthday' => [
                            'rules' => [
                                'require' => ['message' => '追加するお子さんの誕生日は必須です']
                            ],
                        ],
                    ]
                ],
                'User.Child',
                [
                    'resource' => 'Child',
                    'field' => 'sex',
                    'code' => 104,
                    'message' => '追加するお子さんの性別は必須です'
                ]
            ],
            [
                new ServerRequest(['post' => ['add_children' => [['name' => 'イカイカイカ全然勝てないんゴゴゴゴゴ']]]]),
                [
                    'add_children' => [
                        'name' => [
                            'rules' => [
                                'lengthBetween' => [
                                    'option' => [5, 10],
                                    'message' => '文字数は5文字以上10以内で入力してください'
                                ],
                            ]
                        ],
                    ]
                ],
                'User.Child',
                [
                    'resource' => 'Child',
                    'field' => 'name',
                    'code' => 105,
                    'message' => '文字数は5文字以上10以内で入力してください'
                ]
            ],
        ];
    }
}
