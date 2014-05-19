<?php

use Lio\Accounts\MemberNotFoundException;
use Lio\Accounts\UseCases\LoginMemberThroughGithubRequest;
use Lio\Accounts\UseCases\RegisterMemberRequest;
use Lio\CommandBus\CommandBus;
use Lio\Github\GithubAuthenticator;

class AuthController extends BaseController
{
    /**
     * @var Lio\Github\GithubAuthenticator
     */
    private $github;

    public function __construct(CommandBus $bus, GithubAuthenticator $github)
    {
        $this->bus = $bus;
        $this->github = $github;
    }

    public function getLogin()
    {
        if ( ! Input::has('code')) {
            return Redirect::to($this->github->getAuthUrl());
        }

        $githubUser = $this->github->authorize(Input::all());

        $request = new LoginMemberThroughGithubRequest($githubUser);

        try {
            $member = $this->bus->execute($request);
        } catch (MemberNotFoundException $e) {
            dd($githubUser);
            Session::put('githubUser', $githubUser);
            return Redirect::action('AuthController@getSignupConfirm');
        }

        Auth::login($member);
        Session::forget('userGithubData');
        return $this->redirectIntended(action('ForumThreadsController@getIndex'));
    }

    public function getLogout()
    {
        Auth::logout();
        return Redirect::action('ForumThreadsController@getIndex');
    }

    // page that a user sees if they try to do something that requires an authed session
    public function getLoginRequired()
    {
        $this->render('auth.loginrequired');
    }

    // the confirmation page that shows a user what their new account will look like
    public function getSignupConfirm()
    {
        if ( ! Session::has('githubUser')) {
            return Redirect::action('AuthController@getLogin');
        }

        $this->render('auth.signupconfirm', [
            'githubUser' => Session::get('githubUser'),
        ]);
    }

    // actually creates the new user account
    public function postSignupConfirm()
    {
        if ( ! Session::has('githubUser')) {
            return Redirect::action('AuthController@getLogin');
        }

        $githubUser = Session::get('githubUser');

        $request = new RegisterMemberRequest(
            $githubUser->name,
            $githubUser->email,
            $githubUser->githubUrl,
            $githubUser->githubId,
            $githubUser->imageUrl
        );

        $member = $this->bus->execute($request);

        Auth::login($member, true);
        Session::forget('githubUser');

        return $this->redirectIntended(action('ForumThreadsController@getIndex'));
    }
}
