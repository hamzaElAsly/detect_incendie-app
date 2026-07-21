@extends('layouts.guest',['title'=>'503']) @section('content')<x-ui.error-state code="503" :title="__('common.error_503_title')" :description="__('common.error_503_description')"/>@endsection
