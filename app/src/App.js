import React, {Component} from 'react';
import './App.css';


export default class App extends Component {

  constructor(props) {
    super();
    let api = null;
    let url = null;

    if (window.location.href.includes('http://localhost')) {
      url ='http://localhost:3000';
      api = 'http://localhost:83/api';
    }
    else
    {
      url= 'https://www.dashkube.com';
      api ='https://www.dashkube.com/api';
    }

    this.state = {
      url: url,
      api: api,
    }
  }


  componentDidMount() {

  }

  render() {
    return (
      <p>App.js</p>
    );
  }

}

