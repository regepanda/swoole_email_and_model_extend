<?php

namespace App\Admin\Controllers;

use App\Image;

use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Layout\Content;
use App\Http\Controllers\Controller;
use Encore\Admin\Controllers\ModelForm;

class ImageController extends Controller
{
    use ModelForm;

    /**
     * Index interface.
     *
     * @return Content
     */
    public function index()
    {
        return Admin::content(function (Content $content) {

            $content->header('header');
            $content->description('description');
            $content->body($this->grid());
        });
    }

    /**
     * Edit interface.
     *
     * @param $id
     *
     * @return Content
     */
    public function edit($id)
    {
        return Admin::content(function (Content $content) use ($id) {

            $content->header('header');
            $content->description('description');

            $content->body($this->form()->edit($id));
        });
    }

    /**
     * Create interface.
     *
     * @return Content
     */
    public function create()
    {
        return Admin::content(function (Content $content) {

            $content->header('header');
            $content->description('description');

            $content->body($this->form());
        });
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        return Admin::grid(Image::class, function (Grid $grid) {

            $grid->id('ID')->sortable();

            $grid->name()->editable();

            $grid->url()->image('http://swoole.com', 100, 100);
        });
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        return Admin::form(Image::class, function (Form $form) {

            $form->display('id', 'ID');
            $form->text('name', 'name');
//            $form->text('url', 'url');


            // 修改上传目录
            $form->image('url')->move('upload/image');

            // 使用随机生成文件名 (md5(uniqid()).extension)
//            $form->image('picture')->uniqueName();

            // 自定义文件名
//            $form->image('picture')->name(function ($file) {
//                return 'test.' . $file->guessExtension();
//            });
        });
    }
}
