@extends('layouts.guest',['title'=>'500']) @section('content')<x-ui.error-state code="500" :title="__('common.error_500_title')" :description="__('common.error_500_description')"/>@endsection
