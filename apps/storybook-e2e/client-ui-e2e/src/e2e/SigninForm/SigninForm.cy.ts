describe('client-ui: SigninForm component', () => {
  beforeEach(() => cy.visit('/iframe.html?id=signinform--primary'));

  it('should render the component', () => {
    cy.get('h1').should('contain', 'Welcome to SigninForm!');
  });
});
