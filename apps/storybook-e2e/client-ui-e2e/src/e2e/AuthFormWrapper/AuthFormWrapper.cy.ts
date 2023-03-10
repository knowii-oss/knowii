describe('client-ui: AuthFormWrapper component', () => {
  beforeEach(() => cy.visit('/iframe.html?id=authformwrapper--primary'));

  it('should render the component', () => {
    cy.get('h1').should('contain', 'Welcome to AuthFormWrapper!');
  });
});
