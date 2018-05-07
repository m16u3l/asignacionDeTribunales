<?php

namespace App\Http\Controllers;

use Excel;
use App\Student;
use App\Area;
use App\Assignement;
use App\Professional;
use App\Profile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Input;

class ProfileController extends Controller
{
	public function list_profile_finalized(Request $request)
	{
		$profiles = Profile::where('count','>=',3)
												->where('profile_finalized',true)
												->search_by_title_or_student($request->name)
												->orderBy('title')
												->paginate(5);

		return view('profile.list_profile_finalized', compact('profiles'));
	}
	public function list_profiles_signed(Request $request)
	{
		$profiles = Profile::where('count','>=',3)
												->where('profile_finalized',false)
												->search_by_title_or_student($request->name)
												->orderBy('title')
							        	->paginate(5);

		return view('profile.list_profile_assigned', compact('profiles'));
	}
	public function list_profile(Request $request)
	{
		$profiles = Profile::where('count','<',3 )
											->Letters()
											->search_by_title_or_student($request->name)
											->orderBy('title')
											->paginate(5);

		 return view('profile.list_profile', compact('profiles'));
	}

	public function finalizar_perfil(Request $request)
	{
	  	$profile = Profile::find($request->profile_id);
      $now = new \DateTime();

			$profile1 = Profile::find($request->profile_id);
			$profile1->profile_finalized=true;
			$profile1->finalized_date=$now->format('d-m-Y');
			$profile1->save();

			foreach ($profile->assingements as &$professional) {
				DB::table('professionals')->where('id', $professional->id)->decrement('count');
	 		}
			return redirect('perfiles/asignados');
	}

	public function index()
	{

	}

	public function create()
	{

	}

	public function store(Request $request)
	{

	}

	public function show($id)
	{
		//
	}

	public function edit($id)
	{
		//
	}

	public function update(Request $request, $id)
	{
		//
	}

	public function destroy($id)
	{

	}

	public function uploadProfiles($value='')
    {
      return view('import.import_profiles');
    }

    public function importProfiles(Request $request)
    {
      $file = Input::file('fileProfiles');
      	Excel::load($file, function($reader)
        {
       	  foreach ($reader->get() as $key => $value) {

       	  	$profile = Profile::where('title', $value->titulo_proyecto_final)
       	  				->where('objective', $value->objetivo_general)
       	  				->where('degree_modality', $value->modalidad_titulacion)
       	  				->first();

       	  	if (is_null($profile)) {
       	  	 	$profile = new Profile;
          		$profile->title = $value->titulo_proyecto_final;
          		$profile->objective = $value->objetivo_general;
          		$profile->degree_modality  = $value->modalidad_titulacion;
          		$profile->save();
       	  	 } 

       	  	
       	  	$student = Student::where('student_name', $value->nombre_postulante)
       	  				->where('student_last_name_father', $value->apellido_paterno_postulante)
       	  				->where('student_last_name_mother', $value->apellido_materno_postulante)
       	  				->where('career', $value->carrera)
       	  				->first();

       	  	if (is_null($student)) {
       	  		$student = new student;
				$student->student_name = $value->nombre_postulante;
				$student->student_last_name_father = $value->apellido_paterno_postulante;
				$student->student_last_name_mother = $value->apellido_materno_postulante;
				$student->career = $value->carrera;
				$student->save();
       	  	}

			$student->profiles()->attach($profile->id);


          	$professional_tutor = Professional::where('professional_name', $value->nombre_tutor)
          					->where('professional_last_name_father', $value->apellido_paterno_tutor)
							//->where('professional_last_name_mother', $value->apellido_materno_tutor)
							->first();
			if(!is_null($professional_tutor)) {

				$professional_tutor->profiles_tutors()->attach($profile->id);	
			}
          }
        });
      return view('import.import_profiles');
    }
}
