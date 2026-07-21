@extends('layouts.guest',['title'=>'404']) @section('content')<x-ui.error-state code="404" :title="__('common.error_404_title')" :description="__('common.error_404_description')"/>@endsection
