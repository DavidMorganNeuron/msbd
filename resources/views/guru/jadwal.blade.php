@extends('layouts.app')
@section('title', 'Jadwal Mengajar')
@section('heading', 'Jadwal Mengajar')
@section('sub', 'Jadwal mengajar pribadimu untuk mata pelajaran dan kelas yang ditugaskan.')
@section('content')
<x-jadwal-harian
    :jadwal="$jadwalHarian"
    :hari="$hariAktif"
    detail-key="kelas"
    detail-label="Kelas"
    id-prefix="guru"
/>
@endsection